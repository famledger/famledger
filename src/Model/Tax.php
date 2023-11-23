<?php

namespace App\Model;

class Tax
{
    public function __construct(
        private readonly string  $tipo,
        private readonly string  $claveImpuesto,
        private readonly string  $tipoFactor,
        private readonly ?string $tasaOCuota,
        private readonly ?int    $importe,
        private readonly int     $baseImpuesto
    ) {
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getClaveImpuesto(): string
    {
        return $this->claveImpuesto;
    }

    public function getTipoFactor(): string
    {
        return $this->tipoFactor;
    }

    public function getTasaOCuota(): ?float
    {
        return $this->tasaOCuota;
    }

    public function getImporte(): float
    {
        return round($this->importe / 100, 2);
    }

    public function getBaseImpuesto(): float
    {
        return round($this->baseImpuesto / 100, 2);
    }

    public function serialize(): array
    {
        return array_filter([
            'tipo'          => $this->getTipo(),
            'claveImpuesto' => $this->getClaveImpuesto(),
            'tipoFactor'    => $this->getTipoFactor(),
            'tasaOCuota'    => $this->getTasaOCuota(),
            'importe'       => $this->getImporte(),
            'baseImpuesto'  => $this->getBaseImpuesto()
        ]);
    }

}
