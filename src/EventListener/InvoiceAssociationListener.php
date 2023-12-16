<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Event\Invoice\InvoiceUpdatedEvent;
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
                $uuid               = $relacion['Comprobantes'][0];
                $substitutedInvoice = $this->invoiceRepository->findOneBy(['uuid' => $uuid]);
                $substitutedInvoice->setSubstitutedByInvoice($invoice);
                break;

            default:
                break;
        }
    }
}