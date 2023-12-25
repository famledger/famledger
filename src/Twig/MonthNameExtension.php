<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MonthNameExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('month_name', [$this, 'monthName']),
        ];
    }

    public function monthName(?int $month): string
    {
        // TODO: use src/Service/MonthConverter adding a locale parameter
        $months = array_flip([
            'enero'      => 1,
            'febrero'    => 2,
            'marzo'      => 3,
            'abril'      => 4,
            'mayo'       => 5,
            'junio'      => 6,
            'julio'      => 7,
            'agosto'     => 8,
            'septiembre' => 9,
            'octubre'    => 10,
            'noviembre'  => 11,
            'diciembre'  => 12,
        ]);

        return $months[$month] ?? '';
    }
}
