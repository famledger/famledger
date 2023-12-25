<?php

namespace App\Event;

use App\Entity\Document;

class DocumentPreCreateEvent
{
    private Document $document;
    private array    $relatedDocuments = [];

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setRelatedDocuments(array $documents): void
    {
        $this->relatedDocuments = $documents;
    }

    public function getRelatedDocuments(): array
    {
        return $this->relatedDocuments;
    }
}