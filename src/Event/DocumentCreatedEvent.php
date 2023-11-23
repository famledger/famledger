<?php

namespace App\Event;

use App\Entity\Account;
use App\Entity\Document;
use App\Service\DocumentSpecs\BaseDocumentSpecs;

class DocumentCreatedEvent
{
    private Document          $document;
    private BaseDocumentSpecs $documentSpecs;
    private ?Account          $account;

    public function __construct(Document $document, BaseDocumentSpecs $documentSpecs, ?Account $account)
    {
        $this->document      = $document;
        $this->documentSpecs = $documentSpecs;
        $this->account       = $account;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getDocumentSpecs(): BaseDocumentSpecs
    {
        return $this->documentSpecs;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }
}