<?php

namespace App\Exception;

use Exception;

class MissingAccountantFileException extends Exception
{
    public function __construct(string $filepath)
    {
        parent::__construct('Missing accountant file: ', $filepath);
    }
}
