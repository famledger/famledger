<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Exception\InvoiceAssociationException;
use App\Repository\InvoiceRepository;

/**
 * Handles the linking of substituted invoices
 */
#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated')]
class InvoiceAssociationListener
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @throws InvoiceAssociationException
     */
    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $invoice = $event->getInvoice();

        $peticion = json_decode($invoice->getData('peticion'), true);
        $relacion = $peticion['CFDi']['ComprobantesRelacionados'][0] ?? null;
        if (null === $relacion) {
            return;
        }

        switch($relacion['tipoRelacion']) {

            case '04':
                $uuid = $relacion['Comprobantes'][0];
                // check whether the uuid has the form 'ED9B09C8-4A74-4DEA-80AB-889009A23C90'
                if (preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $uuid)) {
                    // lookup by uuid
                    $substitutedInvoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
                } elseif (preg_match('/^[A-F]+-[0-9]+$/i', $uuid)) {
                    // lookup by series and number
                    [$series, $number] = explode('-', $uuid);
                    $substitutedInvoice = $this->invoiceRepository->findOneBy([
                        'series' => $series,
                        'number' => $number
                    ]);
                } else {
                    throw new InvoiceAssociationException("Invoice association error. Unsupported uuid format: '$uuid'");
                }

                if (null === $substitutedInvoice) {
                    throw new InvoiceAssociationException("Invoice association error. Couldn't find related invoice by reference: '$uuid'");
                }

                $substitutedInvoice->setSubstitutedByInvoice($invoice);
                break;

            default:
                break;
        }
    }
}