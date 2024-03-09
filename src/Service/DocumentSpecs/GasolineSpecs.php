<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class GasolineSpecs extends ExpenseSpecs
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::TAX;
    }

    public function setCaptureLine(?string $captureLine): self
    {
        $this->captureLine = $captureLine;

        return $this;
    }

    public function getCaptureLine(): ?string
    {
        return $this->captureLine;
    }

    public function serialize(): array
    {
        return array_merge(
            parent::serialize(),
            [
                'captureLine' => $this->getCaptureLine(),
            ]
        );
    }
}
