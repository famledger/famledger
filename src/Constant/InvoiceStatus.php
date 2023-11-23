<?php

namespace App\Constant;

class InvoiceStatus
{
    const VIGENTE   = 'vigente';
    const CANCELADO = 'cancelado';

    static public function getOptions(): array
    {
        return [
            ucfirst(self::VIGENTE)   => self::VIGENTE,
            ucfirst(self::CANCELADO) => self::CANCELADO,
        ];
    }
}