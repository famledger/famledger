<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Exception\DocumentCreationException;
use App\Exception\StatementValidationFailedException;
use App\Repository\AttachmentRepository;
use App\Repository\DocumentRepository;

class StatementService
{
    public function __construct(
        private readonly AttachmentRepository   $attachmentRepository,
        private readonly DocumentRepository     $documentRepository,
        private readonly DocumentService        $documentService,
        private readonly EntityManagerInterface $em
    ) {
    }

    static public function buildDocumentName(Statement $statement): string
    {
        return sprintf('00 Estado de Cuenta %s %s-%02d.pdf',
            $statement->getAccountNumber(),
            $statement->getYear(),
            $statement->getMonth()
        );
    }

    public function validate(Statement $statement): void
    {
        try {
            foreach ($statement->getTransactions() as $transaction) {
                $this->validateTransaction($transaction);
            }
        } catch (StatementValidationFailedException $e) {
            $statement->setStatus(Statement::STATUS_PENDING);
        }
        $statement->setStatus(Statement::STATUS_CONSOLIDATED);
    }

    /**
     * @throws StatementValidationFailedException
     */
    private function validateTransaction(Transaction $transaction): void
    {
        if (Transaction::STATUS_CONSOLIDATED !== $transaction->getStatus()) {
            throw new StatementValidationFailedException(sprintf('Transaction %d is not consolidated',
                $transaction->getSequenceNo()
            ));
        }

        // TODO: check whether all expenses have a corresponding attachment
        //       probably not possible for legacy months
    }

    public function linkDocument(Transaction $transaction, ?Document $document): void
    {
        $transaction->addDocument($document);
        $document
            ->setSequenceNo($transaction->getSequenceNo())
            ->setFinancialMonth($transaction->getStatement()->getFinancialMonth());
    }

    /**
     * Linking an invoice to a transaction means:
     * - setting the payment date of the invoice to the booking date of the transaction
     * - creating a document for the invoice and linking it to the transaction and financial month
     * - looking up the attachment for the invoice and linking it to the transaction and financial month
     * - associating the document with the attachment
     * - copying the invoice files from the invoices folder to the accounting folder
     *
     * @throws Exception
     * @throws Throwable
     */
    public function linkInvoice(Transaction $transaction, Invoice $invoice): void
    {
        if (null !== $invoice->getAttachment()) {
            throw new DocumentCreationException(sprintf('Invoice %s already has an attachment.', $invoice));
        }
        if (null !== $invoice->getDocument()) {
            throw new DocumentCreationException(sprintf('Invoice %s already has a document.', $invoice));
        }

        $financialMonth = $transaction->getStatement()->getFinancialMonth();

        // create an attachment for the invoice and link it to the transaction and financial month
        $invoiceAttachment = $this->documentService->createAttachmentFromInvoice($transaction, $invoice)
            ->setAccount($financialMonth->getAccount())
            ->setFinancialMonth($financialMonth);

        // create a document for the invoice and link it to the transaction and financial month
        $invoiceDocument = $this->documentService->createDocumentFromInvoice($transaction, $invoice)
            ->setFinancialMonth($financialMonth)
            ->setAttachment($invoiceAttachment);

        // mark the invoice document as paid
        $invoice->setPaymentDate($transaction->getBookingDate());// copy invoice files from the invoices folder to the accounting folder

        // only persist if no exception has been thrown so far
        $this->em->persist($invoiceAttachment);
        $this->em->persist($invoiceDocument);
    }

    public function unLinkDocument(?Transaction $transaction, Document $document): void
    {
        // invoice documents require that the corresponding accounting files are removed
        // non-invoice documents are just un-linked by removing the association to the transaction
        if (null !== $invoice = $document->getInvoice()) {

            // remove invoice files from the accounting folder
            $this->documentService->removeDocument($document);
            if (null !== $attachment = $invoice->getAttachment()) {
                $this->documentService->removeDocument($attachment);
            }

            $invoice->setPaymentDate(null);
        } else {
            $transaction->removeDocument($document);
        }
    }
}