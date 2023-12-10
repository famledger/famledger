<?php

namespace App\Service\Invoice;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use App\Entity\Invoice;
use App\Entity\Series;
use App\Entity\Tenant;
use App\Event\Invoice\InvoiceCreatedEvent;
use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Exception\EfClientException;
use App\Exception\EfStatusException;
use App\Repository\InvoiceRepository;
use App\Repository\SeriesRepository;
use App\Service\EFClient;
use App\Service\LiveModeContext;
use App\Service\TenantContext;

class InvoiceSynchronizer
{
    public function __construct(
        private readonly EFClient                 $client,
        private readonly EntityManagerInterface   $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly InvoiceRepository        $invoiceRepo,
        private readonly LiveModeContext          $liveModeContext,
        private readonly SeriesRepository         $seriesRepository,
        private readonly TenantContext            $tenantContext,
    ) {
    }

    /**
     * @throws EfClientException
     */
    public function fetchActiveSeries(?Tenant $tenant = null): array
    {
        // The LivemodeFilter and TenantFilter are only enabled in the web context,
        // so we can expect to get series un-filtered here.
        $seriesByTenant = $this->seriesRepository->getActiveSeriesByTenant($tenant);

        // we have grouped the series by tenant, because the series code is unique per tenant but could be duplicated
        // across tenants, this allows us to display the tenant RFC in combination with the series code in the report
        $report = [];
        foreach ($seriesByTenant as $rfc => $tenantSeries) {
            foreach ($tenantSeries as $series) {
                $modes = 'api' === strtolower($series->getSource()) ? [true, false] : [true];
                foreach ($modes as $liveMode) {
                    $reportKey = sprintf('%s-%s-%s',
                        $rfc,
                        $series->getCode(),
                        $liveMode ? 'live' : 'test'
                    );
                    // update all invoices for series->code / series->tenant / liveMode
                    $report[$reportKey] = $this->synchronizeSeries($series, $liveMode);
                }
            }
        }

        return $report;
    }

    /**
     * Fetches all invoices from EF for the provided series and livemode and creates corresponding Invoice entities
     * for the ones that don't exist yet. It returns the number of invoices that were added.
     *
     * @throws EfClientException
     * @throws Exception
     */
    public function synchronizeSeries(Series $series, bool $liveMode): int
    {
        $existingInvoices = $this->invoiceRepo->findExistingNumbersForSeries($series, $liveMode);

        // before calling the EF API, set the livemode and tenant contexts from the series
        $this->liveModeContext->setLiveMode($liveMode);
        $this->tenantContext->setTenant($series->getTenant());
        $invoices = $this->client->listInvoices($series->getCode(), new DateTime('2017-01-01'));

        $countAdded = 0;
        foreach (($invoices['Comprobantes'] ?? []) as $invoiceData) {
            if (in_array($invoiceData['folio'], $existingInvoices)) {
                continue;
            }
            $invoice = $this->initInvoiceFromData(
                InvoiceFactory::create($series),
                $invoiceData,
                $series->getTenant(),
                $liveMode
            );
            $this->em->persist($invoice);
            $this->dispatcher->dispatch(new InvoiceCreatedEvent($invoice));
            $countAdded++;
        }

        return $countAdded;
    }

    /**
     * Updates an existing invoice with the latest data from EF and triggers the InvoiceUpdatedEvent
     * Listeners to this event have to take care of
     * - downloading the corresponding PDF and XML files
     * - creating/updating the corresponding Attachment entity
     * - updating a potential Document entity
     *
     * @throws EfClientException
     */
    public function syncInvoiceDetails(Invoice $invoice): void
    {
        $previousInvoice = clone $invoice;

        // set the livemode context from the invoice
        $this->liveModeContext->setLiveMode($invoice->getLiveMode());
        // set the tenant context from the invoice
        $this->tenantContext->setTenant($invoice->getTenant());
        // fetch the invoice data from EF and update the existing invoice by setting the data property
        $invoiceData = $this->client->getInvoice($invoice, true);
        if (isset($invoiceData['mensajeError'])) {
            $mensajeError = $invoiceData['mensajeError'];
            throw new EfStatusException($mensajeError['descripcionError'], $mensajeError['codigoError'] ?? 0);
        }
        $invoice->setData($invoiceData);
        $this->dispatcher->dispatch(new InvoiceUpdatedEvent($invoice, $previousInvoice));
        $invoice->setIsComplete(true);
    }


    /**
     * @throws Exception
     */
    private function initInvoiceFromData(Invoice $invoice, array $invoiceData, Tenant $tenant, bool $liveMode): Invoice
    {
        $issueDate = new DateTime($invoiceData['fecha']);
        $invoice
            ->setTenant($tenant)
            ->setIssueDate($issueDate)
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
}