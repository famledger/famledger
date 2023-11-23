<?php

namespace App\Service\Strategies\Attachment;

use App\Exception\DocumentMatchException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class BTMAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'Consultoria Integral BTM')
               and str_contains($content, 'RFC: CIB1401207M1');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/Total\s+([0-9,.]+)\s+/',
            $reducedContent,
            $filePath,
            'a pagar:'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        $cases = [
            25 => 4,
            19 => 3
        ];

        $found     = false;
        $exception = null;
        foreach ($cases as $width => $height) {
            try {
                $cellContent = StrategyHelper::extractBlock('/CORRESPONDIENTE/', $content, $width, $height);
                // match 'CORRESPONDIENTE AL MES DE OCTUBRE 2023' and extract the month and year
                [$month, $year] = StrategyHelper::extractValues(
                    '/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE) (\d{4})/',
                    $cellContent,
                    $filePath,
                    'CORRESPONDIENTE AL MES DE ...'
                );

                $month = StrategyHelper::convertMonthToInt($month);
                $year  = (int)$year;
                $found = true;
            } catch (DocumentMatchException $e) {
                $exception = $e;
            }
        }
        if (!$found) {
            throw $exception;
        }

//        $cellContent = StrategyHelper::extractBlock('/CORRESPONDIENTE/', $content, 25, 4);
//        // match 'CORRESPONDIENTE AL MES DE OCTUBRE 2023' and extract the month and year
//        [$month, $year] = StrategyHelper::extractValues(
//            '/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE) (\d{4})/',
//            $cellContent,
//            $filePath,
//            'CORRESPONDIENTE AL MES DE ...'
//        );
//
//        $month = StrategyHelper::convertMonthToInt($month);
//        $year  = (int)$year;

        $rfc = StrategyHelper::extractValue('/R.F.C.:\s+(MOPM670510J8A)/',
            $content,
            $filePath,
            'Cliente: MAYELA MONROY PACHECO'
        );

        return (new AttachmentSpecs())
            ->setDisplayFilename(sprintf('Gasto Contabilidad %d-%02d.pdf', $year, $month))
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