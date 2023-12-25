<?php

namespace App\Event;

use App\Entity\Document;

class DocumentRebuildEvent
{
    private Document $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }
}