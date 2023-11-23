<?php

namespace App\Service;

use App\Constant\DocumentType;
use App\Entity\Attachment;
use App\Entity\Document;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;

/**
 * DocumentFactory is responsible for creating Document entities for either:
 * - the DocumentService which is creating documents for different consolidation scenarios
 *   - when an invoice is attached to a transaction both an Income and an Attachment document are created
 *   - when the user has created an annotation document
 * - the InboxHandler that will provide a DocumentSpecs object
 */
class DocumentFactory
{
    static public function create(DocumentType $type): Document
    {
        // We don't need to set the document type on attachments as there is only one type of attachments
        // and the document type is being set implicitly by the class itself.
        return (DocumentType::ATTACHMENT === $type)
            ? new Attachment()
            : (new Document())->setType($type);
    }

    static public function createFromDocumentSpecs(BaseDocumentSpecs $documentSpecs): Document
    {
        // All document specs contain the suggested file name for the document to be created.
        // Attachments might also contain a display filename which is used to display the attachment in the UI
        // making sure the original filename is not modified.
        $document = self::create($documentSpecs->getDocumentType())
            ->setAmount($documentSpecs->getAmount())
            ->setFilename($documentSpecs->getSuggestedFilename())
            ->setSpecs($documentSpecs->serialize());

        if ($documentSpecs instanceof AttachmentSpecs and $document instanceof Attachment) {
            $document->setDisplayFilename($documentSpecs->getDisplayFilename());
        }

        return $document;
    }
}