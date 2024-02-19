<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class ReceiptSpecs extends ExpenseSpecs
{
    private ?string $propertySlug = null;
    private ?string $filePath     = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::RECEIPT;
    }

    public function setPropertySlug(?string $propertySlug): static
    {
        $this->propertySlug = $propertySlug;

        return $this;
    }

    public function getPropertySlug(): ?string
    {
        return $this->propertySlug;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function serialize(): array
    {
        return array_merge(
            parent::serialize(),
            [
                'propertySlug' => $this->getPropertySlug(),
                'filePath'     => $this->getFilePath(),
            ]
        );
    }
}

