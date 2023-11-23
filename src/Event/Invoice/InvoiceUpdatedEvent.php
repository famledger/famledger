<?php

namespace App\Event\Invoice;

use App\Entity\Invoice;

/**
 * Event triggered when invoice details have been updated, either after the initial creation or
 * due to a triggered synchronization (e.g. status change).
 */
class InvoiceUpdatedEvent extends BaseInvoiceEvent
{
    private Invoice $previousInvoice;

    public function __construct(Invoice $invoice, Invoice $previousInvoice)
    {
        parent::__construct($invoice);
        $this->previousInvoice = $previousInvoice;
    }

    public function getPreviousInvoice(): Invoice
    {
        return $this->previousInvoice;
    }
}