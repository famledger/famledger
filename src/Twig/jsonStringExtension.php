<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class jsonStringExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('json', [$this, 'json'], ['is_safe' => ['html']]),
            new TwigFilter('json_string', [$this, 'jsonString'], ['is_safe' => ['html']]),
        ];
    }

    public function json(?array $json = null): string
    {
        if ($json === null) {
            return '';
        }

        return htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT));
    }

    public function jsonString(?string $json = null): string
    {
        return null === $json ? 'null' : $this->json(json_decode($json, true));
    }
}
