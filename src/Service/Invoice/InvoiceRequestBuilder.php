<?php

namespace App\Service\Invoice;

use App\Entity\Customer;

class InvoiceRequestBuilder extends BaseRequestBuilder
{
    public function setCustomer(Customer $customer, string $invoiceUsage): self
    {
        return parent::setCustomer($customer, $invoiceUsage);
    }
}