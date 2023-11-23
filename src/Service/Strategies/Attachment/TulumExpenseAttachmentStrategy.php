<?php

namespace App\Service\Strategies\Attachment;

use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class TulumExpenseAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'ACC010621N93');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/Total\s+\$\s+([0-9,.]+)\s+/',
            $reducedContent,
            $filePath,
            'Total  $ 2,880.00'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        // match 'Descripción CUOTA MANTENIMIENTO AGOSTO 2023' and extract the month and year
        [, $month, $year] = StrategyHelper::extractValues(
            '/Descripción CUOTA (MANTENIMIENTO|MANTENTENIMIENTO) (ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE) (\d{4})/',
            $reducedContent,
            $filePath,
            'Descripción CUOTA MANTENIMIENTO ...'
        );
        $month = StrategyHelper::convertMonthToInt($month);
        $year  = (int)$year;

        $rfc = StrategyHelper::extractValue('/RFC receptor:\s+(MOPM670510J8A)/',
            $content,
            $filePath,
            'RFC receptor: MOPM670510J8A'
        );

        return (new AttachmentSpecs())
            ->setPropertyKey('TUL')
            ->setDisplayFilename(sprintf('Gasto MNTO TULUM %d-%02d.pdf', $year, $month))
            ->setAmount($amount)
            ->setYear($year)
            ->setMonth($month)
            ->setAccountNumber('MOPM670510J8A' === $rfc ? '1447391412' : null);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}