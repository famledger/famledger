<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class EnlaceFiscalExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return str_contains($content, 'FACTURACION ELECTRONICA')
               or str_contains($content, 'Número de convenio: 1422286');
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof ExpenseSpecs) {
            throw new FilenameSuggestionException('expecting ExpenseSpecs', $filePath);
        }

        // Gasto Facturacion Electronica 2023-08
        return sprintf('Gasto Facturacion Electronica %d-%02d.pdf', $documentSpecs->getYear(),
            $documentSpecs->getMonth());
    }
}