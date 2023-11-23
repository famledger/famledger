<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FileIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('file_icon', [$this, 'getFileIcon']),
        ];
    }

    public function getFileIcon(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return match (strtolower($extension)) {
            'pdf'   => 'fa fa-file-pdf',
            'txt'   => 'fa fa-file-text-o',
            'xml'   => 'fa fa-file-code',
            default => 'fa fa-file'
        };
    }
}
