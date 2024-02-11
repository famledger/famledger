<?php

namespace App\Service\Strategies\Expense;

use App\Exception\FilenameSuggestionException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\DonationSpecs;
use App\Service\Strategies\BaseBBVAExpenseStrategy;

class DonationStrategy extends BaseBBVAExpenseStrategy
{
    private array $donees = [
        '062691008404783945' => 'Emilio Miridis',
        '?'                  => 'Alessa Miridis',
    ];

    protected function specificMatchLogic(array $properties, string $content): bool
    {
        return in_array(($properties['Cuenta destino'] ?? ''), array_keys($this->donees));
    }

    public function parse(string $content, ?string $filePath = null): DonationSpecs
    {
        return new DonationSpecs(array_merge(
            $this->getExpenseData($content, $filePath)
        ));
    }

    /**
     * @throws FilenameSuggestionException
     */
    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof DonationSpecs) {
            throw new FilenameSuggestionException('expecting ExpenseSpecs', $filePath);
        }

        return sprintf('Donacion %s %d-%02d.pdf',
            $this->donees[$this->properties['Cuenta destino']] ?? 'unknown',
            $documentSpecs->getYear(),
            $documentSpecs->getMonth()
        );
    }
}