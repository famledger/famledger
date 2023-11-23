<?php

namespace App\Service\Strategies\Income;

use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\InvoiceSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class InvoiceStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return (str_contains($content, 'FACTURA') or str_contains($content, 'RECIBO DE HONORARIOS'))
               and str_contains($content, 'ESTE DOCUMENTO ES UNA REPRESENTACIÓN IMPRESA DE UN CFDI');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {

        [$series, $folio] = StrategyHelper::extractValues(
            '/SERIE\sY\s+FOLIO:\s+([A-Z]+)\s*-\s*([0-9]+)/',
            $content,
            $filePath,
            'SERIE Y FOLIO:'
        );

        return (new InvoiceSpecs())
            ->setSeries($series)
            ->setFolio($folio)
            ->setSuggestedFilename('Ingreso %d-%02d %s.pdf');
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}