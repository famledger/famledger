<?php

namespace App\Service;

class ChecksumHelper
{
    static public function get(string $content): string
    {
        return hash('sha256', $content);
    }
}