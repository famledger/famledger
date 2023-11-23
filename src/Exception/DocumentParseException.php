<?php

namespace App\Exception;

use Throwable;

class DocumentParseException extends ProcessingException
{
    private ?string $filePath;

    public function __construct(?string $filePath, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $message = "Error parsing document: {$filePath}. {$message}";
        parent::__construct($message, $code, $previous);

        $this->filePath = $filePath;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}