<?php

namespace App\Exception;

use Exception;

use App\Entity\Document;

class DuplicateFileException extends Exception
{
    public function __construct(Document $document, Document $conflictingDocument)
    {
        $message = sprintf("Duplicate file: %s: The Document '%s' [%s] already has this checksum: %s.",
            $document->getFilename(),
            $conflictingDocument->getFilename(),
            $conflictingDocument->getId(),
            $document->getChecksum(),
        );
        parent::__construct($message);
    }
}