<?php

namespace App\Model;

class TaxCalculator
{
    private array $taxes      = [];
    private float $retentions = 0;
    private float $transfers  = 0;

    public function __construct(
        string               $taxCategory,
        private readonly int $amount,
    ) {
        $taxes = $this->getTaxesBasedOnCategory($taxCategory, $amount);
        foreach ($taxes as $tax) {
            /** @var Tax $tax */
            if ($tax->getTipo() === 'retencion') {
                $this->retentions += $tax->getImporte();
            } else {
                $this->transfers += $tax->getImporte();
            }
            $this->taxes[] = $tax;
        }
    }

    public function getSubTotal(): float
    {
        return round($this->amount / 100, 2);
    }

    public function getTotal(): float
    {
        return $this->getSubTotal() + $this->transfers - $this->retentions;
    }

    private function getTaxesBasedOnCategory(string $category, int $amount): array
    {
        $taxObjects = [];

        switch($category) {
            case 'Extranjero':
                $taxObjects[] = new Tax('traslado', 'IVA', 'exento', null, null, $amount);
                break;

            case 'Arrendamiento AE':
                $iva          = $amount * 0.16;
                $taxObjects[] = new Tax('traslado', 'IVA', 'tasa', 0.16, (int)round($iva), $amount);
                break;

            case 'Arrendamiento PM':
                // IVA
                $iva          = $amount * 0.16;
                $taxObjects[] = new Tax('traslado', 'IVA', 'tasa', 0.16, (int)round($iva), $amount);

                // ISR
                $retentionIsr = $amount * 0.10;
                $taxObjects[] = new Tax('retencion', 'ISR', 'tasa', 0.10, (int)round($retentionIsr), $amount);

                // Retention on IVA
                $retentionIva = $amount * 0.106667;
                $taxObjects[] = new Tax('retencion', 'IVA', 'tasa', 0.106667, (int)round($retentionIva), $amount);
                break;

            case 'Arrendamiento PF':
                // IVA exempted
                $taxObjects[] = new Tax('traslado', 'IVA', 'exento', 0.0, 0.0, $amount);
                break;
        }

        return $taxObjects;
    }

    public function getTaxes(): array
    {
        return [
            'Totales'   => ['retenciones' => $this->retentions, 'traslados' => $this->transfers],
            'Impuestos' => array_map(function (Tax $tax) {
                return $tax->serialize();
            }, $this->taxes)
        ];
    }
}