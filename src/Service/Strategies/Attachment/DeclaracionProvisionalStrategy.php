<?php

namespace App\Service\Strategies\Attachment;

use App\Constant\DocumentSubType;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class DeclaracionProvisionalStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'DECLARACIÓN PROVISIONAL O DEFINITIVA DE IMPUESTOS FEDERALES')
            or str_contains($content, 'ión Provisional o Definitiva de Impuestos Federales');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        // match 'PERIODO  Enero'
        if (false === preg_match('/Período de la declaración:\s+([A-Za-z]+)/', $content, $matches)) {
            return null;
        }
        $month = StrategyHelper::convertMonthToInt($matches[1]);

        // match 'EJERCICIO  2023
        if (false === preg_match('/Ejercicio:\s+([0-9]+)/', $content, $matches)) {
            return null;
        }
        $year = $matches[1];

        // match RFC: ..
        $rfc = StrategyHelper::extractValue(
            '/RFC:?\s+([0-9A-Z]+)/',
            $content,
            $filePath,
            'RFC: ...'
        );

        return (new AttachmentSpecs())
            ->setAccountNumber(StrategyHelper::getAccountNumber($rfc))
            ->setSubType(DocumentSubType::TAX_CALCULUS) // TODO: not sure whether sub-types are of any use yet
            ->setYear($year)
            ->setMonth($month);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}