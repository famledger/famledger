<?php

namespace App\Exception;

use Exception;

use App\Entity\Document;

class MissingDocumentFileException extends Exception
{
    public function __construct(Document $document)
    {
        parent::__construct(sprintf('The file corresponding to document %d does not exist: %s',
            $document->getId(),
            $document->getFilename()
        ));
    }
}
