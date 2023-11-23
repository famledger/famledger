<?php

namespace App\Exception;

use App\Entity\Invoice;

class InvoiceStatusChangedEvent
{
    private Invoice $invoice;
    private string  $previousStatus;

    public function __construct(Invoice $invoice, string $previousStatus)
    {
        $this->invoice        = $invoice;
        $this->previousStatus = $previousStatus;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }
}