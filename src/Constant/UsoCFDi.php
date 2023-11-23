<?php

namespace App\Constant;

final class UsoCFDi
{
    private array $data = [
        ['G01', 'Adquisición de mercancías.', 'adquisicion_mercancias'],
        ['G02', 'Devoluciones, descuentos o bonificaciones.', 'devolucion_desc_bonif'],
        ['G03', 'Gastos en general.', 'gastos'],
        ['I01', 'Construcciones.', 'construcciones'],
        ['I02', 'Mobiliario y equipo de oficina por inversiones.', 'mobilario'],
        ['I03', 'Equipo de transporte.', 'equipo_transporte'],
        ['I04', 'Equipo de computo y accesorios.', 'equipo_computo'],
        ['I05', 'Dados, troqueles, moldes, matrices y herramental.', 'herramientas'],
        ['I06', 'Comunicaciones telefónicas.', 'comunicaciones_telefonicas'],
        ['I07', 'Comunicaciones satelitales.', 'comunicaciones_satelitales'],
        ['I08', 'Otra maquinaria y equipo.', 'otra_maquinaria'],
        ['D01', 'Honorarios médicos, dentales y gastos hospitalarios.', 'gastos_medicos'],
        ['D02', 'Gastos médicos por incapacidad o discapacidad.', 'gastos_medicos_incapacidad'],
        ['D03', 'Gastos funerales.', 'gastos_funerales'],
        ['D04', 'Donativos.', 'donativos'],
        ['D05', 'Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación).', 'intereses_hipotecarios'],
        ['D06', 'Aportaciones voluntarias al SAR.', 'aportaciones_sar'],
        ['D07', 'Primas por seguros de gastos médicos.', 'primas_seguro_gastos_medicos'],
        ['D08', 'Gastos de transportación escolar obligatoria.', 'gastos_transportacion_escolar'],
        ['D09', 'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones.', 'depositos_ahorro'],
        ['D10', 'Pagos por servicios educativos (colegiaturas).', 'colegiaturas'],
        ['S01', 'Sin efectos fiscales.', 'sin_efectos_fiscales'],
        ['CP01', 'Pagos', 'pagos'],
        ['CN01', 'Nómina', 'nomina']
    ];

    static public function getOptions(): array
    {
        $liveOptions = ['G03', 'S01'];

        $filteredOptions = array_filter((new self())->data, fn($option) => in_array($option[0], $liveOptions));

        return array_reduce($filteredOptions, function ($carry, $item) {
            $carry[$item[1] . ' [' . $item[0] . ']'] = $item[0];

            return $carry;
        }, []);
    }
}
