<?php

namespace App\Exception;

use Exception;

use App\Entity\Invoice;

class InvoiceException extends Exception
{
    public function __construct(Invoice $invoice, string $message)
    {
        parent::__construct(sprintf('Invoice %s: %s', $invoice->__toString(), $message));
    }
}