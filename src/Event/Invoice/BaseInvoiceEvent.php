<?php

namespace App\Event\Invoice;

use App\Entity\Invoice;

class BaseInvoiceEvent
{
    private Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }
}