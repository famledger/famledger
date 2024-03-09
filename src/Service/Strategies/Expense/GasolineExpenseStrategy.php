<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class GasolineExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return (($properties['Cuenta asociada'] ?? null) == '646180110370146350'
                or ($properties['Cuenta destino'] ?? null) == '646180110370146350');
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
        return sprintf('Gasto gasolina Broxel - %s.pdf', $this->properties['Referencia'] ?? $this->properties['Número de referencia']);
    }
}