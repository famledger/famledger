<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Expense\CAPExpenseStrategy;
use App\Service\Strategies\Expense\EnlaceFiscalExpenseStrategy;
use App\Service\Strategies\Expense\GenericExpenseStrategy;
use App\Service\Strategies\Expense\PABExpenseStrategy;
use App\Service\Strategies\Expense\TaxExpenseStrategy;
use App\Service\Strategies\Expense\TulumExpenseStrategy;

class TaxPaymentDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new TaxExpenseStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::TAX;
    }
}
