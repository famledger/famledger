<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Expense\CAPExpenseStrategy;
use App\Service\Strategies\Expense\EnlaceFiscalExpenseStrategy;
use App\Service\Strategies\Expense\GenericExpenseStrategy;
use App\Service\Strategies\Expense\NafinsaExpenseStrategy;
use App\Service\Strategies\Expense\PABExpenseStrategy;
use App\Service\Strategies\Expense\TulumExpenseStrategy;

class ExpenseDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new CAPExpenseStrategy(),
            new EnlaceFiscalExpenseStrategy(),
            new NafinsaExpenseStrategy(),
            new PABExpenseStrategy(),
            new TulumExpenseStrategy(),

            new GenericExpenseStrategy(), // keep this as the last item
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::EXPENSE;
    }
}
