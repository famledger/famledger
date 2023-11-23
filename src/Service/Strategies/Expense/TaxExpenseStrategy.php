<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\DocumentSpecs\TaxSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class TaxExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return str_contains($content, 'Pago Referenciado SAT');
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof TaxSpecs) {
            throw new FilenameSuggestionException('expecting ExpenseSpecs', $filePath);
        }

        return sprintf('Pago Impuesto %d-%02d.pdf',
            $documentSpecs->getYear(),
            $documentSpecs->getMonth() - 1
        );
    }

    public function parse(string $content, ?string $filePath = null): ExpenseSpecs
    {
        return new TaxSpecs($this->getExpenseData($content, $filePath));
    }
}