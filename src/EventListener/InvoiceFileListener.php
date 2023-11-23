<?php

namespace App\EventListener;

use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Service\InvoiceFileManager;

#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated', priority: 1)]
class InvoiceFileListener
{
    public function __construct(
        private readonly InvoiceFileManager $invoiceFileManager
    ) {
    }

    /**
     * When invoice details are updated, either
     * - as part of the invoice creation process or
     * - due to a triggered synchronization (e.g. status change)
     * both the PDF and XML file need to be downloaded or updated.
     *
     * @throws Exception
     */
    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $this->invoiceFileManager->fetchOrUpdateInvoiceFiles($event->getInvoice());
    }
}
