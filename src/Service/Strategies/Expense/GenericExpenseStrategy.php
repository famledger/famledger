<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class GenericExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return true;
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
        return sprintf('Gasto - %s.pdf',
            $this->properties['INTERNAT AMERICAN SCHOOL OF'] ?? $this->properties['Nombre del beneficiario'] ?? 'no identificado'
        );
    }
}