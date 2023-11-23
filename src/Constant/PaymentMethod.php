<?php

namespace App\Constant;

final class PaymentMethod
{
    static public function getOptions(): array
    {
        return [
            'Pago en una sola exhibición [PUE]'      => 'PUE',
            'Pago en parcialidades o diferido [PPD]' => 'PPD'
        ];
    }

}
