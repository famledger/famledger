<?php

namespace App\Service;

use App\Service\DocumentSpecs\AttachmentSpecs;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly string                    $keepInboxFiles,
        private readonly InboxFileManager          $inboxFileManager,
        private readonly DocumentLoader            $documentLoader,
        private readonly FinancialMonthService     $financialMonthService,
        private readonly AccountingDocumentService $accountingDocumentService,
        private readonly AttachmentFolderManager   $attachmentFolderManager,
        private readonly EntityManagerInterface    $em,
        private readonly EventDispatcherInterface  $dispatcher,
        private readonly LoggerInterface           $logger
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
            $document      = DocumentFactory::createFromDocumentSpecs($documentSpecs);

            $accountNumber = $documentSpecs->getAccountNumber();
            $account       = $this->em->getRepository(Account::class)->findOneBy(['number' => $accountNumber]);
            if (null === $account) {
                throw new ProcessingException(sprintf('No account found for account number: %s', $accountNumber));
            }

            if ($document instanceof Attachment) {
                $document->setAccount($account);
                $this->attachmentFolderManager->createAttachmentFile(
                    $account,
                    $filePath,
                    $filename ?? $document->getFilename(),
                );
            } else {
                $financialMonth = $this->financialMonthService->getOrCreateFinancialMonth(
                    $documentSpecs->getYear(),
                    $documentSpecs->getMonth(),
                    $account
                );
                $this->accountingDocumentService->addDocument($document, $financialMonth, $filePath, true);
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