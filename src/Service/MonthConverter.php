<?php

namespace App\Service;

class MonthConverter
{
    static public function fromNumericMonth(?int $month, ?bool $abbreviated = false): string
    {
        return match ($abbreviated) {
            true  => match ($month) {
                1       => 'JAN',
                2       => 'FEB',
                3       => 'MAR',
                4       => 'APR',
                5       => 'MAY',
                6       => 'JUN',
                7       => 'JUL',
                8       => 'AUG',
                9       => 'SEP',
                10      => 'OCT',
                11      => 'NOV',
                12      => 'DEC',
                default => '???',
            },
            false => match ($month) {
                1       => 'January',
                2       => 'February',
                3       => 'March',
                4       => 'April',
                5       => 'May',
                6       => 'June',
                7       => 'July',
                8       => 'August',
                9       => 'September',
                10      => 'October',
                11      => 'November',
                12      => 'December',
                default => 'Invalid month',
            }
        };
    }
}