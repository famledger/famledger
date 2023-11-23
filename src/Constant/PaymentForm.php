<?php

namespace App\Constant;

final class PaymentForm
{
    private array $data = [
        ['01', 'Efectivo', 'efectivo'],
        ['02', 'Cheque nominativo', 'cheque'],
        ['03', 'Transferencia electrónica de fondos', 'transferencia'],
        ['04', 'Tarjeta de crédito', 'tarjeta_credito'],
        ['05', 'Monedero electrónico', 'monedero'],
        ['06', 'Dinero electrónico', 'dinero_electronico'],
        ['08', 'Vales de despensa', 'vales'],
        ['12', 'Dación en pago', 'dacion'],
        ['13', 'Pago por subrogación', 'pago_subrogacion'],
        ['14', 'Pago por consignación', 'pago_consignacion'],
        ['15', 'Condonación', 'condonacion'],
        ['17', 'Compensación', 'compensacion'],
        ['23', 'Novación', 'novacion'],
        ['24', 'Confusión', 'confusion'],
        ['25', 'Remisión de deuda', 'remision'],
        ['26', 'Prescripción o caducidad', 'prescripcion'],
        ['27', 'A satisfacción del acreedor', 'satisfaccion_acreedor'],
        ['28', 'Tarjeta de débito', 'tarjeta_debito'],
        ['29', 'Tarjeta de servicios', 'tarjeta_servicio'],
        ['30', 'Aplicación de anticipos', 'aplicacion_anticipos'],
        ['99', 'Por definir', 'otro']
    ];

    static public function getOptions(): array
    {
        $liveOptions = ['03', '99'];

        $filteredOptions = array_filter((new self())->data, fn($option) => in_array($option[0], $liveOptions));

        return array_reduce($filteredOptions, function ($carry, $item) {
            $carry[$item[1] . ' [' . $item[0] . ']'] = $item[0];

            return $carry;
        }, []);
    }

}
