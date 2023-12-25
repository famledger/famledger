<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class TaxNoticeSpecs extends AttachmentSpecs
{
    protected ?string $captureLine = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::TAX_NOTICE;
    }

    public function getCaptureLine(): ?string
    {
        return $this->captureLine;
    }

    public function setCaptureLine(?string $captureLine): self
    {
        $this->captureLine = $captureLine;

        return $this;
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
