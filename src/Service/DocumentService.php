<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

use App\Constant\DocumentType;
use App\Entity\Account;
use App\Entity\Attachment;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Receipt;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Exception\DocumentCreationException;
use App\Exception\StatementCreationException;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\Accounting\AttachmentFolderManager;
use App\Service\DocumentDetector\DocumentLoader;

/**
 * DocumentService is responsible for:
 * - instantiating different document types using the DocumentFactory
 * - providing the paths to the corresponding physical files using AccountingFolderManager and AttachmentFolderManager
 */
class DocumentService
{
    public function __construct(
        private readonly AccountingDocumentService $accountingDocumentService,
        private readonly AccountingFolderManager   $accountingFolderManager,
        private readonly AttachmentFolderManager   $attachmentFolderManager,
        private readonly DocumentLoader            $documentLoader,
        private readonly InvoiceFileManager        $invoiceFileManager,
        private readonly LoggerInterface           $logger,
        private readonly string                    $accountingFolder,
        private readonly string                    $attachmentsRootFolder,
    ) {
    }

    /**
     * Determines the path of the physical file of the provided Document (or sub-classed entity).
     * This can work in 2 different modes depending on the $searchMode parameter:
     * - searchMode = false (default): the method will return the path where the file is supposed to be located without
     *                      checking if the file actually exists. This is useful when creating a new document.
     * - searchMode = true: the method will determine the path where the file is supposed to be located and will check
     *                      if the file actually exists. If it is not found at the expected location, the method will
     *                      perform additional lookup which involves searching the attachment folder or using the
     *                      document's checksum to find the file.
     *
     * All documents associated with a financial month. must reside in the accounting folder, otherwise
     * they reside in the attachment folder.
     *
     * @throws Exception
     */
    public function getFilepath(
        Document $document,
        ?bool    $absolute = true,
        ?bool    $searchMode = false
    ): ?string {
        // perform some consistency checks (might be obsolete)
        // - documents without a filename cannot be looked up
        // - search mode can only be used for existing documents
        if (null === $document->getFilename()) {
            throw new Exception('Document has no filename');
        }
        if (true === $searchMode and null === $document->getId()) {
            throw new Exception('Search mode can only be used for existing documents');
        }

        $filePath = (null === $financialMonth = $document->getFinancialMonth())
            ? $this->attachmentFolderManager->getAttachmentFolderPath(
                $document->getAccount(),
                $absolute
            ) . '/' . $document->getFilename()
            : $this->accountingFolderManager->getAccountingFolderPath(
                $financialMonth,
                $document->isAttachment(),
                $absolute
            ) . '/' . AccountingDocumentService::composeFilename($document);

        if (false === $searchMode or is_file($filePath)) {
            return $filePath;
        }

        // file not found at the expected location, and search mode is enabled
        // notify this condition on Sentry
        $this->logger->error(sprintf('Lookup of document %d in search mode failed. filename=%s type=%s, class=%s',
            $document->getId(),
            $document->getFilename(),
            $document->getType()->value,
            get_class($document)
        ));

        // search for the document's checksum in both accounting and attachment folder
        // in search mode we always return the absolute path
        $registry = new ChecksumRegistry($this->accountingFolder);
        if (null === $filepath = $registry->get($document->getChecksum())) {
            $registry = new ChecksumRegistry($this->attachmentsRootFolder);
            if (null === $filepath = $registry->get($document->getChecksum())) {
                return null;
            }
        }

        return $filepath;
//
//        $isAttachment = $document->isAttachment();
//        $isAnnex      = $document->isAnnex();
//        // only attachments ,ay not be associated with a financial month
//        if (null === $financialMonth and false === $isAttachment and false === $document->isAnnotation()) {
//            throw new Exception('Document is not an attachment and has no financial month');
//        }
//
//        if ($isAttachment or $isAnnex) {
//            // for legacy reasons, attachments might be stored in the attachment folder
//            // even when they have been associated with a financial month
//            $attachmentPath = $this->attachmentFolderManager->getAttachmentFolderPath(
//                    $document->getAccount(),
//                    $absolute
//                ) . '/' . $document->getFilename();
//            if (($searchMode and is_file($attachmentPath)) or (null === $financialMonth)) {
//                return $attachmentPath;
//            }
//        }
//
//        return $this->accountingFolderManager->getAccountingFolderPath(
//                $document->getFinancialMonth(),
//                $document->isAttachment(),
//                $absolute
//            ) . '/' . AccountingDocumentService::composeFilename($document);
    }

    /**
     * Documents representing an invoice PDF file (income) are only created when the invoice is being associated
     * with a transaction.
     *
     * @throws DocumentCreationException
     */
    public function createDocumentFromInvoice(Transaction $transaction, Invoice $invoice): Document
    {
        try {
            $type = $invoice instanceof Receipt ? DocumentType::PAYMENT : DocumentType::INCOME;
            // instantiate a document entity
            $invoiceDocument = DocumentFactory::create($type)
                ->setInvoice($invoice)
                ->setAmount($invoice->getAmount())
                ->setFilename(InvoiceFileNamer::buildDocumentName($invoice));

            // create a copy of the invoice PDF file in the accounting folder
            $this->createDocumentFile($invoiceDocument, $transaction, $this->getSourceFilePath($invoice, 'pdf'));

            return $invoiceDocument;
        } catch (Throwable $e) {
            throw new DocumentCreationException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws Exception
     */
    public function createAttachmentFromInvoice(Transaction $transaction, Invoice $invoice): ?Attachment
    {
        try {
            // determine the path of the invoice XML file
            $sourceFilePath = $this->getSourceFilePath($invoice, 'xml');

            $attachmentSpecs = $this->documentLoader->load($sourceFilePath, 'xml', basename($sourceFilePath));
            /** @var Attachment $attachmentDocument */
            $attachmentDocument = DocumentFactory::createFromDocumentSpecs($attachmentSpecs);
            $attachmentDocument
                ->setInvoice($invoice)
                ->setDescription($attachmentSpecs->getDescription());

            // create a copy of the invoice XML file in the accounting folder
            $this->createDocumentFile($attachmentDocument, $transaction, $this->getSourceFilePath($invoice, 'xml'));

            return $attachmentDocument;
        } catch (Throwable $e) {
            throw new DocumentCreationException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Helper method for common code for invoice PDF and XML file creation
     *
     * @throws StatementCreationException
     * @throws Throwable
     */
    private function createDocumentFile(Document $document, Transaction $transaction, string $sourceFilePath): void
    {
        // this will set the sequence number of the document
        $transaction->addDocument($document);
        $financialMonth = $transaction->getStatement()->getFinancialMonth();
        $this->accountingDocumentService->addDocument($document, $financialMonth, $sourceFilePath);
    }

    /**
     * @throws Exception
     */
    private function getSourceFilePath(Invoice $invoice, string $fileType): string
    {
        $filePathMethod = $fileType === 'pdf' ? 'getPdfPath' : 'getXMlPath';
        $sourceFilePath = $this->invoiceFileManager->$filePathMethod($invoice);

        if (!is_file($sourceFilePath)) {
            throw new Exception(sprintf('Invoice %s file %s does not exist', strtoupper($fileType), $sourceFilePath));
        }

        return $sourceFilePath;
    }

    /**
     * Copies the invoice PDF and XML files from the invoices folder to the accounting folder.
     * The source files being managed by the InvoiceFileManager, the destination files are managed by the
     * AccountingFolderManager.
     * The source filename is defined by the InvoiceFileNamer.
     * The destination filename is the one defined in the document.
     *
     * @throws StatementCreationException
     * @throws Throwable
     */
    public function copyInvoiceFilesToAccountingFolder(Invoice $invoice): void
    {
        $document       = $invoice->getDocument();
        $attachment     = $document->getAttachment();
        $financialMonth = $document->getFinancialMonth();

        $pdfPath = $this->invoiceFileManager->getPdfPath($invoice);
        $xmlPath = $this->invoiceFileManager->getXmlPath($invoice);

        $this->accountingDocumentService->addDocument($document, $financialMonth, $pdfPath);
        $this->accountingDocumentService->addDocument($attachment, $financialMonth, $xmlPath);
    }

    public function removeDocument(Document $document): void
    {
        $this->accountingDocumentService->deleteDocument($document);
    }

    /**
     * Annotation documents are Document entities representing an empty file, the name implying the purpose.
     * As any other Document, they can be linked to a transaction but never be detached from the financial month
     * they were created for. Annotation documents are always stored in the accounting folder.
     *
     * @throws Exception
     */
    public function createAnnotationDocument(Statement $statement, string $filename, int $amount): Document
    {
        // create an empty file with the given name in the accounting folder
        $financialMonth = $statement->getFinancialMonth();

        $this->accountingFolderManager->createAnnotationFile($financialMonth, $filename);

        // create the corresponding document entity
        $document = DocumentFactory::create(DocumentType::ANNOTATION)
            ->setAmount($amount)
            ->setFilename($filename);

        $financialMonth->addDocument($document);

        return $document;
    }

    /**
     * Annex documents are Document entities representing any file that provides additional information.
     * As any other Document, they can be linked to a transaction. They are not created for a specific
     * financial month and therefore appear in all statements until they are linked to a transaction.
     * Annex documents are always stored in the accounting folder.
     *
     * @throws Exception
     */
    public function createAnnexDocument(UploadedFile $file, Account $account): Document
    {
        $filename = $file->getClientOriginalName();
        $this->attachmentFolderManager->createFile($account, $file->getRealPath(), $filename);

        // create the corresponding document entity
        return DocumentFactory::create(DocumentType::ANNEX)
            ->setFilename($filename)
            ->setAccount($account);
    }

    /**
     * This method must be called after the invoice has been updated with the latest data from the EF API.
     * If the status has changed, the filename will change too and must be updated.
     * The renaming of the corresponding physical file is the responsibility of the caller.
     *
     * @throws Exception
     */
    public function updateFromInvoice(Document $document): Document
    {
        if (null === $invoice = $document->getInvoice()) {
            return $document;
        }

        return $document
            ->setAmount($invoice->getAmount())
            ->setFilename(InvoiceFileNamer::buildDocumentName($invoice));
    }

}