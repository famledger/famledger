<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class AttachmentSpecs extends BaseDocumentSpecs
{
    protected ?string $displayFilename = null;
    protected ?string $propertyKey     = null;
    protected ?string $invoiceSeries = null;
    protected ?int    $invoiceNumber = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::ATTACHMENT;
    }

    public function setDisplayFilename(?string $displayFilename): self
    {
        $this->displayFilename = $displayFilename;

        return $this;
    }

    public function getDisplayFilename(): ?string
    {
        return $this->displayFilename;
    }

    public function setPropertyKey(?string $propertyKey): self
    {
        $this->propertyKey = $propertyKey;

        return $this;
    }

    public function getPropertyKey(): ?string
    {
        return $this->propertyKey;
    }

    public function setInvoiceSeries(?string $invoiceSeries): self
    {
        $this->invoiceSeries = $invoiceSeries;

        return $this;
    }

    public function getInvoiceSeries(): ?string
    {
        return $this->invoiceSeries;
    }

    public function setInvoiceNumber(?int $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function getInvoiceNumber(): ?int
    {
        return $this->invoiceNumber;
    }

    public function serialize(): array
    {
        return array_merge(
            parent::serialize(),
            [
                'displayFilename' => $this->getDisplayFilename(),
                'propertyKey'     => $this->getPropertyKey(),
                'description'     => $this->getDescription(),
                'invoiceSeries'   => $this->getInvoiceSeries(),
                'invoiceNumber'   => $this->getInvoiceNumber(),
            ]
        );
    }
}
