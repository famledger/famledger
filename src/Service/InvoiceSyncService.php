<?php

namespace App\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use App\Entity\Invoice;
use App\Entity\Series;
use App\Event\Invoice\InvoiceCreatedEvent;
use App\Exception\EfClientException;
use App\Exception\InvoiceStatusChangedEvent;
use App\Repository\InvoiceRepository;

/**
 * Production operations
 * - create an invoice
 * - fetch invoices from EF
 *
 * Initial operations
 */
class InvoiceSyncService
{
    public function __construct(
        private readonly EFClient                 $efClient,
        private readonly EntityManagerInterface   $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly InvoiceRepository        $invoiceRepo,
        private readonly LiveModeContext          $liveModeContext,
        private readonly TenantContext            $tenantContext,
    ) {
    }

    /**
     * @throws Exception
     */
    public function initInvoiceFromData(Invoice $invoice, array $invoiceData, bool $liveMode): Invoice
    {
        $issueDate = new DateTime($invoiceData['fecha']);
        $invoice->setTenant($this->tenantContext->getTenant())
            ->setIssueDate(new DateTime($invoiceData['fecha']))
            ->setYear($issueDate->format('Y'))
            ->setMonth($issueDate->format('m'))
            ->setSeries($invoiceData['serie'])
            ->setNumber($invoiceData['folio'])
            ->setRecipientRFC($invoiceData['rfcReceptor'])
            ->setRecipientName($invoiceData['nombreReceptor'])
            ->setStatus($invoiceData['estatus'])
            ->setCurrency($invoiceData['moneda'])
            ->setAmount((int)round(floatval($invoiceData['monto']) * 100))
            ->setLiveMode($liveMode); // this prevents the LiveModeListener from setting the live_mode field

        return $invoice;
    }

    public function findInvoice(string $number, string $series): ?Invoice
    {
        return $this->invoiceRepo->findOneBy([
            'number' => $number,
            'series' => $series
        ]);
    }

    /**
     * Fetches invoice details from EF (endpoint informacionCfdi) and overwrites the data property of the invoice.
     *
     * @throws EfClientException
     */
    public function updateInvoice(Invoice $invoice): Invoice
    {
        $invoiceData = $this->efClient->getInvoice($invoice, true);
        $invoice->setData($invoiceData);

        return $invoice;
    }

    /**
     * Retrieves the series available under the current tenant, determines whether they support live- and debug-mode
     * and fetches the invoices for each series/livemode combination.
     *
     * @throws EfClientException
     * @throws Exception
     */
    public function fetchLegacyInvoices(): array
    {
        $series = $this->em->getRepository(Series::class)->findAll();

        $report = [];
        foreach ($series as $serie) {
            $modes = 'api' === strtolower($serie->getSource()) ? [true, false] : [true];
            foreach ($modes as $liveMode) {
                $reportKey = $serie->getCode() . '-' . ($liveMode ? 'live' : 'test');
                $this->liveModeContext->setLiveMode($liveMode);
                $report[$reportKey] = $this->fetchLegacySeriesInvoices($serie);
            }
        }

        return $report;
    }

    /**
     * @throws EfClientException
     * @throws Exception
     */
    private function fetchLegacySeriesInvoices(Series $serie): int
    {
        $code             = $serie->getCode();
        $existingInvoices = [];
        array_map(function (Invoice $invoice) use (&$existingInvoices) {
            $key                    = $invoice->getSeries() . '-' . $invoice->getNumber() . '-' . (int)$invoice->getLiveMode();
            $existingInvoices[$key] = $invoice;
        }, $this->invoiceRepo->findAll());

        $liveMode = $this->liveModeContext->getLiveMode();
        $invoices = $this->efClient->listInvoices($code, new DateTime('2017-01-01'));
        foreach (($invoices['Comprobantes'] ?? []) as $invoiceData) {
            $key             = $invoiceData['serie'] . '-' . $invoiceData['folio'] . '-' . (int)$liveMode;
            $status          = $invoiceData['estatus'];
            $existingInvoice = $existingInvoices[$key] ?? null;
            if (null !== $existingInvoice) {
                if ($status !== ($previousStatus = $existingInvoice->getStatus())) {
                    // this is probably an invoice cancellation
                    $existingInvoice->setStatus($status);

                    // notify the change of status
                    $this->dispatcher->dispatch(new InvoiceStatusChangedEvent($existingInvoice, $previousStatus));
                }
            } else {
                $invoice = $this->initInvoiceFromData(new Invoice(), $invoiceData, $liveMode);
                $this->em->persist($invoice);
                $this->dispatcher->dispatch(new InvoiceCreatedEvent($invoice));
            }
        }

        return count($invoices['Comprobantes'] ?? []);
    }
}