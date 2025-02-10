<?php

namespace App\Service\Strategies\Attachment;

use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class EnlaceFiscalAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return (str_contains($content, 'Facturación Electrónica por Internet') or
                str_contains($content, 'FACTURACION ELECTRONICA POR INTERNET')
               )
               and str_contains($content, 'FEI100224KS6')
                   // add MIJO620503Q60 due toa payment from the wrong account in december 2021
                   and (str_contains($content, 'MOPM670510J8A') or str_contains($content, 'MIJO620503Q60'));
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/ Total:\s\$\s+([0-9,.]+)\s+/i',
            $reducedContent,
            $filePath,
            'Total:'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        if(str_contains($content, 'MOPM670510J8A')) {
            $accountNumber = '1447391412';
        } elseif(str_contains($content, 'MIJO620503Q60')) {
            $accountNumber = '1447302029';
        } else {
            $accountNumber = null;
        }

        return (new AttachmentSpecs())
            ->setDisplayFilename('Gasto Facturacion Electronica.pdf')
            ->setAmount($amount)
            ->setAccountNumber($accountNumber);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}