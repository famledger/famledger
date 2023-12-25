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
        return str_contains($content, 'Pago Referenciado SAT')
               and (
                   str_contains($content, 'Línea de Captura')
                   or str_contains($content, 'Línea de captura')
               );
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
        $captureLine = $this->properties['Línea de Captura'] ?? $this->properties['Línea de captura'];
        // remove all spaces from the capture line and add a space after each 4 characters
        // older documents have no spaces in the capture line
        $captureLine = implode(' ', str_split(str_replace(' ', '', $captureLine), 4));

        return new TaxSpecs(array_merge(
            ['captureLine' => $captureLine],
            $this->getExpenseData($content, $filePath)
        ));
    }
}