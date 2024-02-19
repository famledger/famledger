<?php

namespace App\Service;

use App\Constant\PropertyEDocType;
use App\Entity\EDoc;
use App\Entity\Property;
use App\Event\DocumentPreCreateEvent;
use App\Service\DocumentSpecs\ReceiptSpecs;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use App\Entity\Account;
use App\Entity\Attachment;
use App\Event\DocumentCreatedEvent;
use App\Exception\ProcessingException;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AttachmentFolderManager;
use App\Service\Accounting\FinancialMonthService;
use App\Service\DocumentDetector\DocumentLoader;

class InboxHandler
{
    public function __construct(
        private readonly AccountingDocumentService $accountingDocumentService,
        private readonly AttachmentFolderManager   $attachmentFolderManager,
        private readonly DocumentLoader            $documentLoader,
        private readonly DocumentService           $documentService,
        private readonly EDocService               $eDocService,
        private readonly EntityManagerInterface    $em,
        private readonly EventDispatcherInterface  $dispatcher,
        private readonly FinancialMonthService     $financialMonthService,
        private readonly InboxFileManager          $inboxFileManager,
        private readonly LoggerInterface           $logger,
        private readonly string                    $keepInboxFiles,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function processFiles(): array
    {
        $report = [];
        foreach ($this->inboxFileManager->getFiles(true) as $filePath) {
            $report[basename($filePath)] = $this->processDocument($filePath);
        }
        $this->em->flush();

        return $report;
    }

    /**
     * @throws Throwable
     */
    private function processDocument(string $filePath): ?ProcessingResponse
    {
        try {
            $documentSpecs = $this->documentLoader->load($filePath);
            if ($documentSpecs instanceof ReceiptSpecs) {
                if(null === $eDoc = $this->eDocService->createAndPersistFromDocumentSpecs($documentSpecs)) {
                    throw new ProcessingException('Failed to create eDoc');
                }

                return new ProcessingResponse(ProcessingResponse::TYPE_SUCCESS, $eDoc);
            }

            $document      = DocumentFactory::createFromDocumentSpecs($documentSpecs);
            $accountNumber = $documentSpecs->getAccountNumber();
            $account       = $this->em->getRepository(Account::class)->findOneBy(['number' => $accountNumber]);
            if (null === $account) {
                throw new ProcessingException(sprintf('No account found for account number: %s', $accountNumber));
            }

            // All attachments are initially stored in the attachment folder and moved to the accounting folder
            // when the attachment is linked to a transaction (via the StatementController -> StatementService::linkDocument).
            if ($document instanceof Attachment) {
                $document->setAccount($account);
                $this->attachmentFolderManager->createFile(
                    $account,
                    $filePath,
                    $filename ?? $document->getFilename(),
                );
            } else {
                // All documents can be associated with a financial month as they have a year and month.
                $financialMonth = $this->financialMonthService->getOrCreateFinancialMonth(
                    $documentSpecs->getYear(),
                    $documentSpecs->getMonth(),
                    $account
                );

                // handle related documents (currently tax payments)
                try {
                    $event = $this->dispatcher->dispatch(new DocumentPreCreateEvent($document));
                } catch (Exception $e) {
                    throw new ProcessingException($e->getMessage());
                }
                // Tax notices, and potentially other documents, have to be moved to the accounting folder
                // where the 'parent document' is stored.
                foreach ($event->getRelatedDocuments() as $relatedDocument) {
                    $relatedFilePath = $this->documentService->getFilepath($relatedDocument);
                    $this->accountingDocumentService->addDocument(
                        $relatedDocument,
                        $financialMonth,
                        $relatedFilePath,
                        true
                    );
                }

                // move the payment to the accounting folder
                $this->accountingDocumentService->addDocument(
                    $document,
                    $financialMonth,
                    $filePath,
                    true
                );
            }

            $this->dispatcher->dispatch(new DocumentCreatedEvent($document, $documentSpecs, $account));

            $this->em->persist($document);

            $this->logger->notice(sprintf('DOCUMENT_DETECTOR: Successfully processed document type: %s',
                $documentSpecs->getDocumentType()->value
            ));

            // at this point the inbox file has been copied and can be deleted
            if (!$this->keepInboxFiles) {
                unlink($filePath);
            }

            return new ProcessingResponse(ProcessingResponse::TYPE_SUCCESS, $document);

        } catch (ProcessingException $e) {
            $this->logger->error('DOCUMENT_PROCESSING: Document processing failed: ' . $e->getMessage());

            return new ProcessingResponse(ProcessingResponse::TYPE_ERROR, $e->getMessage());
        }
    }
}