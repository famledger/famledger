<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class InvoiceSpecs extends BaseDocumentSpecs
{
    private ?string $series    = null;
    private ?string $folio     = null;
    private ?string $recipient = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::INCOME;
    }

    /**
     * @return string|null
     */
    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getFolio(): ?string
    {
        return $this->folio;
    }

    public function setFolio(?string $folio): self
    {
        $this->folio = $folio;

        return $this;
    }

    public function serialize(): array
    {
        return array_merge(
            parent::serialize(),
            [
                'series' => $this->getSeries(),
                'folio'  => $this->getFolio()
            ]
        );
    }
}
