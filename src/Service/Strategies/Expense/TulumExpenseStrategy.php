<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class TulumExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return str_contains($content, 'MANT OF 6 Y 8')
               or $properties['Cuenta destino'] ?? '' == '036691500222427302';
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof ExpenseSpecs) {
            throw new FilenameSuggestionException('Invalid document specs', $filePath);
        }

        // Gasto MNTO TULUM 2023-08
        return sprintf('Gasto MNTO TULUM %d-%02d.pdf',
            $documentSpecs->getYear(),
            $documentSpecs->getMonth()
        );
    }

    protected function getPropertyKey(): ?string
    {
        return 'TUL';
    }
}