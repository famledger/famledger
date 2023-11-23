<?php

namespace App\Service\Accounting;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

use App\Constant\DocumentType;
use App\Entity\Document;
use App\Entity\FinancialMonth;
use App\Exception\StatementCreationException;
use App\Repository\DocumentRepository;

/**
 * Handles all accounting related files which are based on so-called financial months.
 *
 * For accounting purposes, files are grouped by financial months and stored under a tenant-specific folder
 *      <root_folder>/accounting/<tenant_rfc>
 * Each financial month defines a multi-level folder structure that contains the files for that month.
 *      <account_number>/<year>/<year>-<month> <month_name>
 *
 * An identical folder structure is created under the accountant's folder:
 *     <root_folder>/accountant/<tenant_rfc>/<account_number>/<year>/<year>-<month> <month_name>
 *
 * Only the files under the accounting folder are manipulated by the application. The accountant's folder is
 * synchronized on a monthly basis in order to provide the accountant with the monthly files on a cloud drive.
 * The AccountingFolderComparator service is responsible for this task.
 *
 * - storing of Document entities in the file system.
 *   - addDocument
 * - assert that the folder structure for a FinancialMonth exists.
 * - synchronize file system folders with FinancialMonth entities.
 *
 */
class AccountingDocumentService
{
    /**
     * Builds the filename for a Document entity.
     * If the document is an attachment, the filename is returned as is.
     * Otherwise, the filename is prefixed with the sequence number of the document.
     */
    static public function composeFilename(Document $document): string
    {
        if ($document->getType() === DocumentType::ACCOUNT_STATEMENT) {
            $document->setSequenceNo(0);
        }

        return ($document->isAttachment() or null === $document->getSequenceNo())
            ? $document->getFilename()
            : sprintf('%02d %s', $document->getSequenceNo(), $document->getFilename());
    }

    public function __construct(
        private readonly AccountingFolderManager $accountingFolderManager,
        private readonly DocumentRepository      $documentRepository,
        private readonly EntityManagerInterface  $em,
    ) {
    }

    /**
     * Receives
     * - a FinancialMonth object
     * - a DocumentSpecs object
     * - a sourceFilepath where the file described by the document specs is
     * located and performs the following operations:
     *
     * - creates and persists a Document entity from the DocumentSpecs object
     * - associates the Document entity with the FinancialMonth entity
     * - copies the file to the target folder identified by the FinancialMonth entity
     * - returns the Document entity
     *
     * The optional $filename parameter can be used to overwrite the suggested filename, otherwise the filename
     * from the DocumentSpecs object is used.
     *
     * @throws StatementCreationException
     * @throws Throwable
     */
    public function addDocument(
        Document       $document,
        FinancialMonth $financialMonth,
        ?string        $sourceFilepath = null
    ): void {
        $mustCopyFile   = null !== $sourceFilepath;
        $copiedFilePath = null;
        try {
            $document->setFinancialMonth($financialMonth);

            if ($mustCopyFile) {
                $isAttachment   = $document->isAttachment();
                $copiedFilePath = $this->accountingFolderManager->createAccountingFile(
                    $financialMonth,
                    $sourceFilepath,
                    self::composeFilename($document),
                    $isAttachment,
                );
            }

            $financialMonth->addDocument($document);
        } catch (Throwable $e) {
            if (is_file($copiedFilePath)) {
                unlink($copiedFilePath);
            }
            throw $e;
        }
    }

    public function deleteDocument(Document $document): void
    {
        try {
            if (null !== $document->getTransaction()) {
                throw new Exception('Cannot delete a document that is associated with a transaction');
            }

            $financialMonth = $document->getFinancialMonth();
            $this->accountingFolderManager->deleteAccountingFile(
                $financialMonth,
                self::composeFilename($document),
                $document->isAttachment()
            );
            $financialMonth->removeDocument($document);
            $this->em->remove($document);
        } catch (Exception) {
        }
    }

    public function renameDocument(Document $document, string $filename): void
    {
        try {
            $document->setFilename($filename);
            $this->em->flush();
        } catch (Exception ) {
        }
    }

    public function getDocumentByChecksum(FinancialMonth $financialMonth, string $checksum): ?Document
    {
        return $this->documentRepository->findByChecksumForFinancialMonth($financialMonth, $checksum);
    }

    /**
     * @throws Exception
     */
    public function getStatementDocument(FinancialMonth $financialMonth): ?Document
    {
        return $financialMonth->getDocumentBySequenceNo(0);
    }
}
