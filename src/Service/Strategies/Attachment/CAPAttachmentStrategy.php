<?php

namespace App\Service\Strategies\Attachment;

use App\Exception\DocumentMatchException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class CAPAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'CORPORATIVO')
               and str_contains($content, 'RFC: CAP0201093L7');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/Total:?\s+\$?([0-9,.]+)\s+/',
            $reducedContent,
            $filePath,
            'Total'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        $cases = [
            40 => 2,
            19 => 4,
            17 => 5,
        ];

        $year      = null;
        $month     = null;
        $found     = false;
        $exception = null;
        foreach ($cases as $width => $height) {
            try {
                $cellContent = StrategyHelper::extractBlock('/CORRESPONDIENTE|CORREPONDIENTES/', $content, $width, $height);
                // match 'CORRESPONDIENTE AL MES DE OCTUBRE 2023' and extract the month and year
                [$month, $year] = StrategyHelper::extractValues(
                    '/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)(?: del)? (\d{4})/i',
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

        $displayFilename = null === $year
            ? 'Gasto Contabilidad.pdf'
            : sprintf('Gasto Contabilidad %d-%02d.pdf', $year, $month);

        return (new AttachmentSpecs())
            ->setDisplayFilename($displayFilename)
            ->setAmount($amount)
            ->setYear($year)
            ->setMonth($month)
            ->setAccountNumber('1447391412');
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}