<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Constant\DocumentType;
use App\Repository\PaymentTransactionRepository;

#[ORM\Entity(repositoryClass: PaymentTransactionRepository::class)]
class PaymentTransaction extends Transaction
{
    public function getReceipt(): ?Receipt
    {
        foreach ($this->getDocuments() as $document) {
            if ($document->getType() === DocumentType::PAYMENT) {
                /** @var Receipt $payment */
                $payment = $document->getInvoice();

                return ($payment instanceof Receipt) ? $payment : null;
            }
        }

        return null;
    }

    public function getInvoicesList(): array
    {
        $numbers = [];
        foreach (parent::getPaidInvoices() as $invoice) {
            $numbers[$invoice->getSeries()][$invoice->getId()] = $invoice->getNumber();
        }

        return $numbers;
    }
}
