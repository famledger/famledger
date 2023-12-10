<?php

namespace App\Service;

use Exception;
use Throwable;

use App\Constant\DocumentType;
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
    ) {
    }

    /**
     * Determines the path of the physical file associated with the given document/attachment.
     *
     * If the document is an attachment and is not linked to a transaction, it is not stored in the accounting folder.
     * In order to determine the path, we must use the AttachmentFolderManager instead of the AccountingFolderManager.
     */
    public function getAccountingFilepath(Document $document, ?bool $absolute = true): ?string
    {
        // TODO: understand and document this case
        if (null === $document->getFilename()) {
            return null;
        }

        // all documents are stored in the accounting folder
        // all attachments are stored in the attachment folder
        // attachments for invoices only exists when they have been associated with a transaction,
        // so we don't have to consider them here
        $folderPath = ($document instanceof Attachment and null == $document->getTransaction())
            ? $this->attachmentFolderManager->getAttachmentFolderPath(
                $document->getAccount(),
                $absolute
            )
            : $this->accountingFolderManager->getAccountingFolderPath(
                $document->getFinancialMonth(),
                $document->isAttachment(),
                $absolute
            );

        return $folderPath . '/' . AccountingDocumentService::composeFilename($document);
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