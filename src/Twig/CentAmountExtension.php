<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CentAmountExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('cent_amount', [$this, 'getCentAmount'], ['is_safe' => ['html']])
        ];
    }

    public function getCentAmount($amount, ?bool $colored = false): string
    {
        $value = number_format($amount / 100, 2);
        if ($colored) {
            $class = $amount < 0 ? 'text-danger' : 'text-success';

            return sprintf('<span class="%s">%s</span>', $class, $value);
        }

        return $value;
    }
}