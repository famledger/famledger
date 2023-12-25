<?php

namespace App\Service\Strategies\Attachment;

use App\Constant\DocumentSubType;
use App\Exception\DocumentParseException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\TaxNoticeSpecs;
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
        [$monthName, $year] = StrategyHelper::extractValues(
            '/Período de la declaración:\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE|Del Ejercicio)\s+Ejercicio:\s+(\d{4})/i',
            $content,
            $filePath,
            'Período de la declaración: Noviembre  Ejercicio:  2023'
        );
        // a null month means that the tax notice is for the whole year
        $month = ($monthName === 'Del Ejercicio') ? 13 : StrategyHelper::convertMonthToInt($monthName);

        // Captura: 0423 7CQZ 8100 4067 9230 Importe a pagar: total $2,210
        $cellContent = StrategyHelper::extractBlock('/Captura:/', $content, 120, 5);
        preg_match('/Captura:\s+(\w{4}\s\w{4}\s\w{4}\s\w{4}\s\w{4})\s/i', $cellContent, $matches);
        //  a declaration with zero amount will not yield any results
        if (isset($matches[1])) {
            $captureLine = $matches[1];

            preg_match('/\$([0-9,]+)/i', $cellContent, $matches);
            $amount = StrategyHelper::convertToIntegerAmount($matches[1]);
        } else {
            $captureLine = null;
            $amount      = 0;
        }
        // match RFC: ..
        $rfc = StrategyHelper::extractValue(
            '/RFC:\s+([0-9A-Z]+)/',
            $content,
            $filePath,
            'RFC: ...'
        );

        return (new TaxNoticeSpecs())
            ->setAccountNumber(StrategyHelper::getAccountNumber($rfc))
            ->setCaptureLine($captureLine)
            ->setAmount($amount)
            ->setYear($year)
            ->setMonth($month);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}