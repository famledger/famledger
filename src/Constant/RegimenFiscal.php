<?php

namespace App\Constant;

final class RegimenFiscal
{
    private array $data = [
        ['601', 'General de Ley Personas Morales', 'No', 'Sí'],
        ['603', 'Personas Morales con Fines no Lucrativos', 'No', 'Sí'],
        ['605', 'Sueldos y Salarios e Ingresos Asimilados a Salarios', 'Sí', 'No'],
        ['606', 'Arrendamiento', 'Sí', 'No'],
        ['607', 'Régimen de Enajenación o Adquisición de Bienes', 'No', 'Sí'],
        ['608', 'Demás ingresos', 'Sí', 'No'],
        ['610', 'Residentes en el Extranjero sin Establecimiento Permanente en México', 'Sí', 'Sí'],
        ['611', 'Ingresos por Dividendos (socios y accionistas)', 'Sí', 'No'],
        ['612', 'Personas Físicas con Actividades Empresariales y Profesionales', 'Sí', 'No'],
        ['614', 'Ingresos por intereses', 'Sí', 'No'],
        ['615', 'Régimen de los ingresos por obtención de premios', 'Sí', 'No'],
        ['616', 'Sin obligaciones fiscales', 'Sí', 'No'],
        ['620', 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos', 'No', 'Sí'],
        ['621', 'Incorporación Fiscal', 'Sí', 'No'],
        ['622', 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'No', 'Sí'],
        ['623', 'Opcional para Grupos de Sociedades', 'No', 'Sí'],
        ['624', 'Coordinados', 'No', 'Sí'],
        ['625', 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'Sí', 'No'],
        ['626', 'Régimen Simplificado de Confianza', 'Sí', 'Sí']
    ];

    static public function getOptions(): array
    {
        $liveOptions = ['601', '605', '611', '616', '626'];

        $filteredOptions = array_filter((new self())->data, fn($option) => in_array($option[0], $liveOptions));

        return array_reduce($filteredOptions, function ($carry, $item) {
            $carry[$item[1] . ' [' . $item[0] . ']'] = $item[0];

            return $carry;
        }, []);
    }
}