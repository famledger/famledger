<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Income\InvoiceStrategy;

class InvoiceDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new InvoiceStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::INCOME;
    }
}
