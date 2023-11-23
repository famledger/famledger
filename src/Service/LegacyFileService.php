<?php

namespace App\Service;

use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

use App\Constant\DocumentType;
use App\Entity\Attachment;
use App\Entity\Document;
use App\Entity\FinancialMonth;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Exception\DocumentConversionException;
use App\Exception\DocumentDetectionException;
use App\Exception\StatementCreationException;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\DocumentDetector\DocumentLoader;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\StatementSpecs;

/**
 * Handles all file related aspects of a FinancialMonth entity
 *
 * LegacyFileService handles various operations related to financial months,
 * transactions, and document management in a multi-tenant environment.
 *
 * Responsibilities:
 * 1. Create, check, and ensure month-specific folders on the file system.
 * 2. Synchronize file system folders with FinancialMonth entities.
 * 3. Add and associate documents with financial months and transactions.
 * 4. Manage the state of FinancialMonth and related entities.
 * 5. Validate and handle documents according to their types and specifications.
 *
 * Dependencies:
 * - TenantContext for multi-tenancy support.
 * - EntityManagerInterface for ORM operations.
 * - FinancialMonthRepository for FinancialMonth entity fetching.
 * - Filesystem for file operations.
 * - LoggerInterface for logging.
 */
class LegacyFileService
{
    public function __construct(
        private readonly AccountingFolderManager   $accountingFolderManager,
        private readonly AccountingDocumentService $accountingDocumentService,
        private readonly PropertyRepository        $propertyRepository,
        private readonly string                    $accountingFolder,
        private readonly DocumentLoader            $documentLoader,
        private readonly EntityManagerInterface    $em,
        private readonly Filesystem                $filesystem,
        private readonly LoggerInterface           $logger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function syncDocuments(FinancialMonth $financialMonth): void
    {
        // find files in the financial month folder (legacy folder) and create the corresponding Document entities
        $this->importDocuments($financialMonth);
        $this->importAttachments($financialMonth);
        // associate documents with transactions by their sequence number
        $this->associateDocumentsWithTransactions($financialMonth);
    }

    /**
     * @throws Exception
     */
    public function copyLegacyFolder(
        FinancialMonth $financialMonth,
        string         $legacyFolder,
        ?string        $targetFolder = null
    ): void {
        // Initialize targetFolder only once during the first call
        if ($targetFolder === null) {
            $targetFolder = $this->accountingFolderManager->getAccountingFolderPath($financialMonth, false);
            // make sure the attachments path also exists
            $this->accountingFolderManager->getAccountingFolderPath($financialMonth, true);
        }

        $dir = opendir($legacyFolder);

        while(false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($legacyFolder . '/' . $file)) {
                    // Recursive call to handle subfolder
                    $this->copyLegacyFolder($financialMonth, $legacyFolder . '/' . $file, $targetFolder . '/' . $file);
                } else {
                    // Create target subfolder if it doesn't exist
                    if (!is_dir(dirname($targetFolder))) {
                        mkdir(dirname($targetFolder), 0777, true);
                    }
                    // Copy the file to the correct target subfolder
                    copy($legacyFolder . '/' . $file, $targetFolder . '/' . $file);
                }
            }
        }

        closedir($dir);
    }

    /**
     * @throws StatementCreationException
     * @throws Throwable
     * @throws DocumentDetectionException
     * @throws DocumentConversionException
     */
    public function importLegacyStatement(string $filePath, FinancialMonth $financialMonth): void
    {
        $tenant = $financialMonth->getTenant();
        /** @var StatementSpecs $documentSpecs */
        $documentSpecs = $this->documentLoader->load($filePath);
        $document      = DocumentFactory::createFromDocumentSpecs($documentSpecs)
            ->setTenant($tenant);
        if ($documentSpecs instanceof AttachmentSpecs and null !== $documentSpecs->getPropertyKey()) {
            $document->setProperty($this->propertyRepository->findOneBy([
                'key' => $documentSpecs->getPropertyKey()
            ]));
        }
        $this->accountingDocumentService->addDocument($document, $financialMonth, $filePath);
        $this->em->persist($document);
        $statement = $documentSpecs->getStatement()
            ->setFinancialMonth($financialMonth)
            ->setAccount($financialMonth->getAccount())
            ->setTenant($tenant)
            ->setDocument($document);
        foreach ($statement->getTransactions() as $transaction) {
            $this->em->persist($transaction);
        }
        $this->em->persist($statement);
        $this->em->persist($financialMonth);
    }

    private function scanDir(string $dir): array
    {
        $results = scandir($dir);

        return array_diff($results, ['.', '..']);
    }

    /**
     * @throws Exception
     */
    public function getFinancialMonthFolder(FinancialMonth $financialMonth, ?bool $absolute = true): string
    {
        $rootFolder = $absolute ? ($this->accountingFolder . '/') : '';

        return sprintf('%s%s/%s',
            $rootFolder,
            $financialMonth->getAccount()->getTenant()->getRfc(),
            $financialMonth->getPath()
        );
    }

    /**
     * @throws Exception
     */
    public function getDocumentPath(Document $document, ?bool $absolute = true): ?string
    {
        // TODO: re-engineer to use AccountingFolderManager
        return (null === $filename = $document->getFilename())
            ? null
            : sprintf('%s%s/%s',
                $this->getFinancialMonthFolder($document->getFinancialMonth(), $absolute),
                DocumentType::ATTACHMENT === $document->getType() ? '/Anexos' : '',
                $filename
            );
    }

    /**
     * @throws Exception
     */
    public function fileExists(Document $document): bool
    {
        return $this->filesystem->exists($this->getDocumentPath($document));
    }

    public function associateDocumentsWithTransactions(FinancialMonth $financialMonth): void
    {
        // can't handle financial months without a statement
        if (null === $financialMonth->getStatement()) {
            return;
        }

        // index documents by sequence number
        // !!! multiple documents can have the same sequence number
        $documents = [];
        foreach ($financialMonth->getDocuments()->toArray() as $document) {
            $documents[$document->getSequenceNo()][] = $document;
        }
        // index transactions by sequence number
        $transactions = [];
        foreach ($financialMonth->getStatement()->getTransactions() as $transaction) {
            /** @var Transaction $transaction */
            $transactions[$transaction->getSequenceNo()] = $transaction;
        }

        // add documents to the transaction with the same sequence number and update the amount if they represent income
        // do not re-associate a document if it already has a transaction
        foreach ($documents as $sequenceNo => $_documents) {
            foreach ($_documents as $document) {
                if (isset($transactions[$sequenceNo])) {
                    $transaction = $transactions[$sequenceNo];
                    // if there is only 1 document, and it is an income, copy the amount from the transaction
                    if ($document->getType() === DocumentType::INCOME and 1 === count($_documents)) {
                        $document->setAmount($transaction->getAmount());
                    }
                    if (null === $document->getTransaction()) {
                        $transaction
                            ->addDocument($document)
                            ->setType($document->getType());
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function importDocuments(FinancialMonth $financialMonth): void
    {
        // we will probably only import documents once, but in case this is run with already existing documents,
        // we need to avoid importing them again
        $existingFilenames = array_map(function (Document $document) {
            return $document->getFilename();
        }, $financialMonth->getDocuments()->toArray());

        $folderFilenames = $this->getFolderFilenames($financialMonth, false);
        $newFilenames = array_diff($folderFilenames, $existingFilenames);
        $obsoleteFilenames = array_diff($existingFilenames, $folderFilenames);

        $folderPath = $this->accountingFolderManager->getAccountingFolderPath($financialMonth, false);
        foreach ($newFilenames as $filename) {
            $filepath = $folderPath . '/' . $filename;
            try {
                // if the document is a statement, load it and create the statement
                // otherwise, create a document entity
                if (DocumentType::ACCOUNT_STATEMENT === $documentType = DocumentHelper::getTypeFromFilename($filename)) {
                    // we expressly do not use DocumentCreatedEvent to trigger the processing of the statement
                    // as we already know the account and financial month
                    /** @var StatementSpecs $documentSpecs */
                    $documentSpecs = $this->documentLoader->load($filepath);
                    $document      = DocumentFactory::createFromDocumentSpecs($documentSpecs)
                        ->setFilename($filename)
                        ->setFinancialMonth($financialMonth)
                        ->setTenant($financialMonth->getTenant());

                    $statement = $documentSpecs->getStatement()
                        ->setAccount($financialMonth->getAccount())
                        ->setDocument($document)
                        ->setTenant($financialMonth->getTenant())
                        ->setStatus(Statement::STATUS_PENDING);

                    $financialMonth->setStatement($statement);
                    $this->em->persist($statement);
                    foreach ($statement->getTransactions() as $transaction) {
                        $this->em->persist($transaction);
                    }
                } else {
                    $document = (new Document())
                        ->setFilename($filename)
                        ->setType($documentType)
                        ->setFinancialMonth($financialMonth)
                        ->setTenant($financialMonth->getTenant());
                    if (DocumentType::ANNOTATION !== $documentType) {
                        try {
                            $documentSpecs = $this->documentLoader->load($filepath);
                            $document
                                ->setSpecs($documentSpecs->serialize())
                                ->setAmount($documentSpecs->getAmount());
                        } catch (Throwable $e) {
                            $this->logger->error($e->getMessage());
                        }
                    }

                }
                $this->em->persist($document);
                $this->em->persist($financialMonth);
                $financialMonth->addDocument($document);

                if (preg_match('/^(\d{2}) .*$/', $filename, $matches)) {
                    $document->setSequenceNo((int)$matches[1]);
                    if(null !== $statement = $financialMonth->getStatement()) {
                        // get the transaction of the statement with the same sequence number as the document
                        if(false !== $transaction = $statement->getTransactions()->filter(function (Transaction $transaction) use ($matches) {
                            return $transaction->getSequenceNo() === (int)$matches[1];
                        })->first()) {
                            $transaction->addDocument($document);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    private function importAttachments(FinancialMonth $financialMonth): void
    {
        // we will probably only import documents once, but in case this is run with already existing documents,
        // we need to avoid importing them again
        $existingFilenames = array_map(function (Document $document) {
            return $document->getFilename();
        }, $financialMonth->getDocuments()->toArray());

        $newFilenames = array_diff($this->getFolderFilenames($financialMonth, true), $existingFilenames);
        $folderPath   = $this->accountingFolderManager->getAccountingFolderPath($financialMonth, true);
        foreach ($newFilenames as $filename) {
            try {
                $filepath = $folderPath . '/' . $filename;
                $document = (new Attachment())
                    ->setFilename($filename)
                    ->setType(DocumentType::ATTACHMENT)
                    ->setFinancialMonth($financialMonth) // do not remove
                    ->setAccount($financialMonth->getAccount())
                    ->setTenant($financialMonth->getTenant())
                    ->setIsLegacy(true);

                try {
                    $documentSpecs = $this->documentLoader->load($filepath);
                    $document
                        ->setSpecs($documentSpecs->serialize())
                        ->setDescription($documentSpecs->getDescription())
                        ->setAmount($documentSpecs->getAmount());
                    if ($documentSpecs instanceof AttachmentSpecs) {
                        $documentSpecs->setDisplayFilename($documentSpecs->getDisplayFilename());
                    }
                } catch (Throwable $e) {
                    $this->logger->error($e->getMessage());
                }
                $this->em->persist($document);
                $financialMonth->addDocument($document);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    private function getFolderFilenames(FinancialMonth $financialMonth, bool $attachments): array
    {
        $montPath  = $this->accountingFolderManager->getAccountingFolderPath($financialMonth, $attachments);
        $documents = $this->scanDir($montPath);
        $newPaths  = [];
        foreach ($documents as $file) {
            if (in_array($file, ['.', '..', '.DS_Store'])) {
                continue;
            }
            if (!is_file($montPath . '/' . $file)) {
                continue;
            }
            $newPaths[] = $file;
        }

        return $newPaths;
    }
}
