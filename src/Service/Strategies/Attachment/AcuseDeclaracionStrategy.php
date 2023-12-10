<?php

namespace App\Service\Strategies\Attachment;

use App\Constant\DocumentSubType;
use App\Exception\DocumentParseException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class AcuseDeclaracionStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'ACUSE DE RECIBO');
//               and str_contains($content, 'DECLARACIÓN PROVISIONAL O DEFINITIVA DE IMPUESTOS FEDERALES');
    }

    /**
     * @throws DocumentParseException
     */
    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        // match 'Período de la declaración:  Enero
        $value = StrategyHelper::extractValue(
            '/Período de la declaración:\s+([A-Za-z]+)/',
            $content,
            $filePath,
            'Período de la declaración:'
        );
        $month = StrategyHelper::convertMonthToInt($value);

        // match 'Ejercicio:  2023
        $year = StrategyHelper::extractValue(
            '/Ejercicio:\s+([0-9]+)/',
            $content,
            $filePath,
            'Ejercicio:'
        );

        // match a pagar:                 $3,371
        $value  = StrategyHelper::extractValue(
            '/a pagar:\s+\$?([0-9,.]+)\s+/',
            $content,
            $filePath,
            'a pagar:'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        // match RFC: ..
        $rfc  = StrategyHelper::extractValue(
            '/RFC:\s+([0-9A-Z]+)/',
            $content,
            $filePath,
            'RFC: ...'
        );

        return (new AttachmentSpecs())
            ->setAccountNumber(StrategyHelper::getAccountNumber($rfc))
            ->setSubType(DocumentSubType::TAX_NOTICE)
            ->setAmount($amount)
            ->setYear($year)
            ->setMonth($month);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}