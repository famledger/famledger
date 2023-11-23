<?php

namespace App\Service\Invoice;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use App\Entity\Invoice;
use App\Entity\InvoiceTask;
use App\Event\Invoice\InvoiceCreatedEvent;
use App\Exception\InvoiceCreationException;
use App\Model\TaxCalculator;
use App\Service\EFClient;
use App\Service\LiveModeContext;

class InvoiceBuilder
{
    public function __construct(
        private readonly EFClient                 $client,
        private readonly EntityManagerInterface   $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly InvoiceRequestComposer   $requestComposer,
        private readonly LiveModeContext          $liveModeContext,
    ) {
    }

    /**
     * @throws InvoiceCreationException
     */
    public function createInvoice(InvoiceTask $invoiceTask): Invoice
    {
        try {
            $request     = $this->buildRequestFromTask($invoiceTask, $totalAmount);
            $apiResponse = $this->client->createInvoice($request);
            if (isset($apiResponse['errores'])) {
                $errors = $apiResponse['errores'];
                throw new InvoiceCreationException($errors[0]['descripcionError']);
            }
            $customer = $invoiceTask->getCustomer();
            $invoice  = $this->initInvoiceFromCreationResponse(new Invoice(), $apiResponse)
                ->setProperty($invoiceTask->getProperty())
                ->setDescription($invoiceTask->getConcept())
                ->setCustomer($customer)
                ->setRecipientRFC($customer->getRfc())
                ->setRecipientName($customer->getName())
                ->setAmount(round($totalAmount * 100))
                // the billing period the invoice corresponds to is the one specified in the task
                // for non-rental invoices this is the current month and year
                ->setMonth($invoiceTask->getMonth())
                ->setYear($invoiceTask->getYear())
                ->setSubstitutesInvoice($invoiceTask->getSubstitutesInvoice());

            $invoiceTask
                ->setRequestData($request)
                ->setInvoice($invoice)
                ->setLastExecuted(new DateTime())
                ->setStatus(InvoiceTask::STATUS_COMPLETED);

            $this->em->persist($invoice);

            $this->dispatcher->dispatch(new InvoiceCreatedEvent($invoice));

            return $invoice;
        } catch (Exception $e) {
            throw new InvoiceCreationException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws Exception
     */
    public function buildRequestFromTask(InvoiceTask $invoiceTask, ?float &$totalAmount): array
    {
        $serie           = $invoiceTask->getSeries();
        $requestComposer = $this->requestComposer
            ->setTemplate(json_decode($invoiceTask->getInvoiceTemplate(), true))
            ->setFolio($this->getNextFolio($serie))
            ->setSerie($serie->getCode())
            ->setPaymentOptions($invoiceTask->getPaymentMethod(), $invoiceTask->getPaymentForm())
            ->setDescription($invoiceTask->getConcept())
            ->setUsoCfdi($invoiceTask->getInvoiceUsage())
            ->setSubstitution($invoiceTask->getSubstitutesInvoice());

        // only property related tasks require the property cadastral number
        if (null !== $property = $invoiceTask->getProperty() and null !== $property->getCadastralNumber()) {
            $requestComposer->setCuentaPredial($property->getCadastralNumber());
        }

        $taxCategory   = $invoiceTask->getTaxCategory();
        $taxCalculator = new TaxCalculator($taxCategory, $invoiceTask->getAmount());
        $requestComposer
            ->setTaxes($taxCalculator->getTaxes())
            ->setAmount($taxCalculator->getSubTotal(), $taxCalculator->getTotal());

        $totalAmount = $taxCalculator->getTotal();

        return $requestComposer->getInvoiceRequest();
    }

    /**
     * @throws Exception
     */
    private function initInvoiceFromCreationResponse(Invoice $invoice, array $invoiceData): Invoice
    {
        $issueDate = new DateTime($invoiceData['fechaGeneracionCFDi']);

        return $invoice
            ->setIssueDate($issueDate)
            ->setSeries($invoiceData['serie'])
            ->setNumber($invoiceData['folioInterno'])
            ->setUrlPdf($invoiceData['descargaArchivoPDF'])
            ->setUrlXml($invoiceData['descargaXmlCFDi'])
            ->setStatus($invoiceData['estadoCFDi'])
            ->setCurrency('MXN')
            ->setAmount(0);
    }

    private function getNextFolio(string $series): string
    {
        $lastInvoice = $this->em->getRepository(Invoice::class)
            ->findOneBy(
                ['series' => $series, 'liveMode' => $this->liveModeContext->getLiveMode()],
                ['number' => 'DESC']
            );

        return $lastInvoice ? $lastInvoice->getNumber() + 1 : 1;
    }
}