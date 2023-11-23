<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class CAPExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return in_array($properties['Cuenta destino'] ?? '', ['072691002207655677', '014691655074448956', '0154489144'])
               or $properties['Beneficiario'] ?? '' == 'CORPORATIVO DE ASESORES PATRIMONIAL';
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof ExpenseSpecs) {
            throw new FilenameSuggestionException('expecting ExpenseSpecs', $filePath);
        }

        // Gasto Contabilidad 2023-08
        return sprintf('Gasto Contabilidad %d-%02d.pdf', $documentSpecs->getYear(), $documentSpecs->getMonth());
    }
}