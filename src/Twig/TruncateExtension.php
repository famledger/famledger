<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TruncateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('truncate', [$this, 'truncate']),
        ];
    }

    public function truncate(string $text, int $length = 25, string $ellipsis = '...'): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . $ellipsis : $text;
    }
}
