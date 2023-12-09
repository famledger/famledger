<?php

namespace App\Exception;

use Exception;

class ExistingFileException extends Exception
{
    public function __construct(string $filepath)
    {
        parent::__construct(sprintf("The file '%s' already exists.", $filepath));
    }
}
