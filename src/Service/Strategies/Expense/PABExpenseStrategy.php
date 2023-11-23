<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class PABExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return $properties['Cuenta destino'] ?? '' == '0116541267'
               or ($properties['Cuenta destino'] ?? '') == '0154489144';
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof ExpenseSpecs) {
            throw new FilenameSuggestionException('expecting ExpenseSpecs', $filePath);
        }

        // Gasto MNTO OFIC 216 2023-08
        return sprintf('Gasto MNTO OFIC 216 %d-%02d.pdf', $documentSpecs->getYear(), $documentSpecs->getMonth());
    }

    protected function getPropertyKey(): ?string
    {
        return 'PAB';
    }
}