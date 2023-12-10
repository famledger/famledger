<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JsonDecodeExtension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            new TwigFilter('json_decode', [$this, 'jsonDecode'], ['is_safe' => ['html']])
        ];
    }

    public function jsonDecode(?string $string): mixed
    {
        return null === $string ? '' : json_decode($string, true);
    }
}