<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Receipt\CFEStrategy;

class ReceiptDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new CFEStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::RECEIPT;
    }
}
