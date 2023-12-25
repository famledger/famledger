<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Exception;

use App\Constant\DocumentType;
use App\Repository\AttachmentRepository;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
class Attachment extends Document
{
    #[ORM\OneToOne(inversedBy: 'attachment')]
    private ?Document $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Account $account = null;

    #[ORM\Column]
    private ?bool $isLegacy = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayFilename = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $invoiceSeries = null;

    #[ORM\Column(nullable: true)]
    private ?int $invoiceNumber = null;

    /**
     * @throws Exception
     */
    public function setTypeString(string $type): static
    {
        throw new Exception('Cannot set type on Attachment');
    }

    public function getParent(): ?Document
    {
        return $this->parent;
    }

    public function setParent(?Document $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getIsLegacy(): bool
    {
        return (bool)$this->isLegacy;
    }

    public function setIsLegacy(bool $isLegacy): static
    {
        $this->isLegacy = $isLegacy;

        return $this;
    }

    public function getDisplayFilename(): ?string
    {
        return $this->displayFilename;
    }

    public function setDisplayFilename(?string $displayFilename): static
    {
        $this->displayFilename = $displayFilename;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getInvoiceSeries(): ?string
    {
        return $this->invoiceSeries;
    }

    public function setInvoiceSeries(?string $invoiceSeries): static
    {
        $this->invoiceSeries = $invoiceSeries;

        return $this;
    }

    public function getInvoiceNumber(): ?int
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?int $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        // unset the owning side of the relation if necessary
        if ($invoice === null && $this->getInvoice() !== null) {
            $this->getInvoice()->setAttachment(null);
        }

        // set the owning side of the relation if necessary
        if ($invoice !== null && $invoice->getAttachment() !== $this) {
            $invoice->setAttachment($this);
        }

        parent::setInvoice($invoice);

        return $this;
    }
}
