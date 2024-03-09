<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use App\Constant\SeriesType;
use App\Repository\ReceiptRepository;

#[ORM\Entity(repositoryClass: ReceiptRepository::class)]
class Receipt extends Invoice
{
    #[ORM\OneToMany(mappedBy: SeriesType::PAYMENT, targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\OneToOne(inversedBy: 'receipt', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?ReceiptTask $task = null;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
    }

    public function getInvoicePeriod(): string
    {
        return '';
    }

    public function getPaymentDate(): ?DateTime
    {
        if (false === $invoice = $this->getInvoices()->first()) {
            return null;
        }

        return $invoice->getPaymentDate();
    }

    public function getStatement(): ?Statement
    {
        return $this->getDocument()?->getTransaction()?->getStatement();
    }

    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function getInvoicesList(): array
    {
        $numbers = [];
        array_map(function (Invoice $invoice) use (&$numbers) {
            $numbers[$invoice->getSeries()][$invoice->getId()] = $invoice->getNumber();
        }, $this->invoices->toArray());

        return $numbers;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setPayment($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getPayment() === $this) {
                $invoice->setPayment(null);
            }
        }

        return $this;
    }

    public function getTask(): ?ReceiptTask
    {
        return $this->task;
    }

    public function setTask(?ReceiptTask $task): static
    {
        $this->task = $task;

        return $this;
    }
}
