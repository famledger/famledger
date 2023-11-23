<?php

namespace App\Exception;

use Throwable;

class DocumentMatchException extends DocumentParseException
{
    public function __construct( string $concept, string $filePath, int $code = 0, Throwable $previous = null)
    {
        $message = "Could not match: $concept";
        parent::__construct($filePath, $message, $code, $previous);
    }
}