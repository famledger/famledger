<?php

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;

use App\Entity\Document;
use App\Exception\DuplicateFileException;
use App\Exception\FileRenameException;
use App\Exception\MissingDocumentFileException;
use App\Repository\DocumentRepository;
use App\Service\ChecksumHelper;
use App\Service\DocumentService;
use App\Service\TenantContext;

/**
 * DocumentListener is responsible for managing the lifecycle events of Document entities within the Doctrine ORM.
 *
 * This listener is triggered before the flush and update operations in Doctrine, handling two main scenarios:
 * 1. New Document Creation (preFlush): It checks and sets the tenant for new documents, calculates
 *    and validates the checksum, and ensures that the checksum is unique across all documents.
 * 2. Document Update (preUpdate): It handles file operations such as renaming, recalculates the checksum,
 *    and checks for any conflicts in checksums.
 *
 * Key operations include tenant assignment (if not already set), checksum calculation, duplication checks,
 * file rename operations, and handling specific exceptions related to document processing.
 */
#[AsDoctrineListener(event: Events::preFlush)]
#[AsDoctrineListener(event: Events::preUpdate)]
class DocumentListener
{
    private array $newChecksums = [];

    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentService    $documentService,
        private readonly TenantContext      $tenantContext,
    ) {
    }

    /**
     * Detect new Document entities that are being persisted and update their checksums.
     * If another document exists with the same checksum, throw a DuplicateFileException is thrown.
     *
     * @throws MissingDocumentFileException
     * @throws DuplicateFileException
     */
    public function preFlush(PreFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $document) {
            if ($document instanceof Document) {
                $this->processNewDocument($document);
            }
        }
    }

    /**
     * @throws MissingDocumentFileException
     * @throws DuplicateFileException
     */
    private function processNewDocument(Document $document): void
    {
        // TODO: this should actually be handled by the TenantListener, maybe it can be removed
        if (null === $document->getTenant()) {
            $document->setTenant($this->tenantContext->getTenant());
        }

        $checksum = $this->getChecksum($document);
        if (!$this->hasEmptyFile($document)) {
            $this->assertChecksumIsUnique($checksum, $document);
        }
        $document->setChecksum($checksum);
    }

    /**
     * @throws DuplicateFileException
     * @throws MissingDocumentFileException
     * @throws FileRenameException
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $document = $args->getObject();
        if ($document instanceof Document and count($args->getEntityChangeSet()) > 0) {
            $oldDocument = $this->getOldEntity($args);
            $this->processUpdatedDocument($oldDocument, $document);
        }
    }

    /**
     * @throws DuplicateFileException
     * @throws MissingDocumentFileException
     * @throws FileRenameException
     */
    private function processUpdatedDocument(Document $oldDocument, Document $document): void
    {
        // Attempt to rename, keeping a backup for potential undo
        $renameBackup = $this->handleFileOperations($oldDocument, $document);

        try {
            // Check for existing checksum before updating
            $this->getChecksum($document);
            $this->checkForExistingChecksum($document);
        } catch (Exception $e) {
            if ($renameBackup) {
                @rename($this->documentService->getAccountingFilepath($document), $renameBackup);
            }
            throw $e;
        }
    }

    private function getOldEntity(PreUpdateEventArgs $args): Document
    {
        /** @var Document $oldDocument */
        $oldDocument = clone($args->getObject());
        foreach ($args->getEntityChangeSet() as $field => $changeSet) {
            $setter = 'set' . ucfirst($field);
            $oldDocument->$setter($args->getOldValue($field));
        }

        return $oldDocument;
    }

    /**
     * @throws MissingDocumentFileException
     * @throws FileRenameException
     */
    private function handleFileOperations(Document $oldDocument, Document $document): ?string
    {
        $sourceFile = $this->documentService->getAccountingFilepath($oldDocument);
        $targetFile = $this->documentService->getAccountingFilepath($document);

        if (!is_file($sourceFile)) {
            throw new MissingDocumentFileException($oldDocument);
        }

        if ($sourceFile !== $targetFile) {
            if (@rename($sourceFile, $targetFile)) {
                return $sourceFile;
            } else {
                throw new FileRenameException($sourceFile, $targetFile);
            }
        }

        return null;
    }

    /**
     * @throws DuplicateFileException
     * @throws MissingDocumentFileException
     */
    private function assertChecksumIsUnique(string $checksum, Document $document): void
    {
        if (isset($this->newChecksums[$checksum])) {
            throw new DuplicateFileException($document, $this->newChecksums[$checksum]);
        }

        $this->newChecksums[$checksum] = $document;

        $this->getChecksum($document);
        $this->checkForExistingChecksum($document);
    }


    /**
     * @throws MissingDocumentFileException
     */
    private function getChecksum(Document $document): string
    {
        $filepath = $this->documentService->getAccountingFilepath($document);
        if (!is_file($filepath)) {
            throw new MissingDocumentFileException($document);
        }

        return $this->hasEmptyFile($document)
            ? ChecksumHelper::get($document->getFilename())
            : ChecksumHelper::get(file_get_contents($filepath));
    }

    /**
     * @throws DuplicateFileException
     */
    private function checkForExistingChecksum(Document $document): void
    {
        // do not check for duplicate checksums on empty files
        if (0 === filesize($this->documentService->getAccountingFilepath($document))) {
            return;
        }

        $conflictingDocument = $this->documentRepository->findOneBy([
            'checksum' => $document->getChecksum(),
        ]);

        if ($conflictingDocument !== null and $conflictingDocument->getId() !== $document->getId()) {
            throw new DuplicateFileException($document, $conflictingDocument);
        }
    }

    private function hasEmptyFile(Document $document): bool
    {
        return 0 === filesize($this->documentService->getAccountingFilepath($document));
    }
}