<?php

namespace App\Service\Strategies\Expense;

use App\Constant\DocumentSubType;
use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ExpenseSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class NafinsaExpenseStrategy extends BaseBBVAExpenseStrategy
{
    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return str_contains($content, 'Banco destino: NAFIN');
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
        return sprintf('Inversion - NAFINSA - %s - %d.pdf',
            $documentSpecs->getIssueDate()->format('Y-m-d'),
            abs(round($documentSpecs->getAmount() / 100))
        );
    }

    public function parse(string $content, ?string $filePath = null): ExpenseSpecs
    {
        return parent::parse($content, $filePath)
            ->setSubType(DocumentSubType::INVESTMENT);
    }
}