<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Statement\BBVACreditCardStrategy;
use App\Service\Strategies\Statement\BBVAStatementStrategy;

class StatementDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new BBVACreditCardStrategy(),
            new BBVAStatementStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::ACCOUNT_STATEMENT;
    }
}
