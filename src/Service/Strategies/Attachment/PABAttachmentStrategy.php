<?php

namespace App\Service\Strategies\Attachment;

use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class PABAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return (str_contains($content, 'Pabellon Caribe') and (str_contains($content, 'PCA0312011M3'))
                or (str_contains($content, 'PABELLON CARIBE') and str_contains($content, 'PPC2101205P1'))
        );
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/Total\s+\$\s*([0-9,.]+)\s+/i',
            $reducedContent,
            $filePath,
            'a pagar:'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        //$cellContent = StrategyHelper::extractBlock('/(?:Pago de )cuota de mantenimiento/i', $content, 53, 2);
        $cellContent = StrategyHelper::extractBlock('/Pago(?:\s+\w+)*\s*cuota\s*de\s*mantenimiento/i', $content, 70, 2);
        if (!empty($cellContent)) {
            // match 'CORRESPONDIENTE AL MES DE OCTUBRE 2023' and extract the month and year
            [$month, $year] = StrategyHelper::extractValues(
                '/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SEPTIEMBRRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)(?: de| d)? (\d{4})/i',
                $cellContent ?? '',
                $filePath,
                'Pago ... cuota de mantenimiento ...'
            );
            // fix for invoice from september 2022
            $month       = strtolower($month) === 'septiembrre' ? 'septiembre' : $month;
            $month       = StrategyHelper::convertMonthToInt($month);
            $year        = (int)$year;
            $description = null;
        } else {
            $year        = null;
            $month       = null;
            $description = StrategyHelper::extractBlock('/Cuota/', $content, 70, 2);
        }

        return (new AttachmentSpecs())
            ->setPropertyKey('PAB')
            ->setAmount($amount)
            ->setYear($year)
            ->setMonth($month)
            ->setAccountNumber('1447391412')
            ->setDescription($description);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}