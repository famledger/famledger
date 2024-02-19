<?php

namespace App\Service\Strategies\Receipt;

use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\ReceiptSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class CFEStrategy implements StrategyInterface
{
    private $propertyMap = [
        'ALB'  => '810210702328',
        'CRA'  => '780910300232',
        'OYA'  => '782000156319',
        'OYA4' => '782881201168',
        'OYAC' => '782000156289',
        'PAB'  => '780091202714',
        'QUI'  => '780000555725',
        'TU6'  => '780010401289',
        'TU8'  => '780150602501',
    ];

    public function matches(string $content, ?string $filePath = null): bool
    {
        return (str_contains($content, 'NO. DE SERVICIO') and str_contains($content, 'PERIODO FACTURADO'));
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        [$noServicio] = StrategyHelper::extractValues(
            '/NO. DE SERVICIO\s*:\s*([0-9]{12})/',
            $content,
            $filePath,
            'NO. DE SERVICIO'
        );
        [$amount] = StrategyHelper::extractValues(
            "/$noServicio [0-9]{6} ([0-9]{9})/",
            $content,
            $filePath,
            'Monto a pagar'
        );
        [$date1, $date2] = StrategyHelper::extractValues(
            '/PERIODO FACTURADO:\s*(\d{2} [A-Z]{3} \d{2}) - (\d{2} [A-Z]{3} \d{2})/',
            $content,
            $filePath,
            'PERIODO FACTURADO'
        );


        $propertySlug = array_search($noServicio, $this->propertyMap);
        $startDate    = StrategyHelper::convertCFEDate($date1);
        $endDate      = StrategyHelper::convertCFEDate($date2);

        return (new ReceiptSpecs())
            ->setFilePath($filePath)
            ->setAmount((int)$amount)
            ->setPropertySlug($propertySlug)
            ->setMonth($endDate->format('m'))
            ->setYear($endDate->format('Y'))
            ->setSuggestedFilename(sprintf('Recibo %s - %s - %s - %d.pdf',
                $propertySlug,
                $endDate->format('Y-m-d'),
                $noServicio,
                (int)$amount
            ));
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return $documentSpecs->getSuggestedFilename();
    }
}