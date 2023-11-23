<?php

namespace App\Service;

use App\Exception\StatementCreationException;
use Exception;

use App\Constant\DocumentType;
use App\Entity\Attachment;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Exception\DocumentConversionException;
use App\Exception\DocumentDetectionException;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\Accounting\AttachmentFolderManager;
use App\Service\DocumentDetector\DocumentLoader;
use Throwable;

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
     * IN order to determine the path, we must use the AttachmentFolderManager instead of the AccountingFolderManager.
     */
    public function getAccountingFilepath(Document $document, ?bool $absolute = true): ?string
    {
        // TODO: understand and document this case
        if (null === $document->getFilename()) {
            return null;
        }

        $folderPath = ($document instanceof Attachment and null === $document->getFinancialMonth())
            ? $this->attachmentFolderManager->getAttachmentFolderPath(
                $document->getAccount(),
                $absolute
            )
            : $this->accountingFolderManager->getAccountingFolderPath(
                $document->getFinancialMonth(),
                $document->isAttachment(),
                $absolute
            );

        return sprintf('%s/%s%s',
            $folderPath,
            (null === $sequenceNumber = $document->getSequenceNo()) ? '' : sprintf('%02d ', $sequenceNumber),
            AccountingDocumentService::composeFilename($document)
        );
    }

    /**
     * Copies the invoice PDF and XML files from the invoices folder to the accounting folder.
     * The source files being managed by the InvoiceFileManager, the destination files are managed by the
     * AccountingFolderManager.
     * The source filename is defined by the InvoiceFileNamer.
     * The destination filename is the one define in the document.
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

    public function removeInvoiceAccountingFiles(Invoice $invoice): void
    {
        if (null !== $document = $invoice->getDocument()) {
            $this->accountingDocumentService->deleteDocument($document);
            if (null !== $attachment = $document->getAttachment()) {
                $this->accountingDocumentService->deleteDocument($attachment);
            }
        }
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
     * Documents representing an invoice PDF file (income) are only created when the invoice is being associated
     * with a transaction. Asserting that a corresponding file is copied to the accounting folder is the
     * responsibility of the caller.
     */
    public function createFromInvoice(Invoice $invoice): Document
    {
        return DocumentFactory::create(DocumentType::INCOME)
            ->setInvoice($invoice)
            ->setAmount($invoice->getAmount())
            ->setFilename(InvoiceFileNamer::getInvoiceDocumentName($invoice));
    }

    /**
     * @throws Exception
     */
    public function createAttachmentFromInvoice(Invoice $invoice): ?Attachment
    {
        $filePath = $this->invoiceFileManager->getXMlPath($invoice);
        if (!is_file($filePath)) {
            throw new Exception(sprintf('Invoice XML file %s does not exist', $filePath));
        }
        try {
            $attachmentSpecs = $this->documentLoader->load($filePath, 'xml', basename($filePath));
            /** @var Attachment $attachment */
            $attachment = DocumentFactory::createFromDocumentSpecs($attachmentSpecs);
            $attachment
                ->setInvoice($invoice)
                ->setDescription($attachmentSpecs->getDescription());

            return $attachment;
        } catch (DocumentConversionException) {
            // TODO: finish implementation
        } catch (DocumentDetectionException) {
        }

        return null;
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
            ->setFilename(InvoiceFileNamer::getInvoiceDocumentName($invoice));
    }
}