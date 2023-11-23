<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Exception\StatementValidationFailedException;
use App\Repository\AttachmentRepository;

class StatementService
{
    public function __construct(
        private readonly AttachmentRepository   $attachmentRepository,
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
        if (null === $attachment = $this->attachmentRepository->findInvoiceAttachment($invoice)) {
            throw new Exception(sprintf('No attachment found for invoice %s', $invoice->getNumber()));
        }

        $financialMonth = $transaction->getStatement()->getFinancialMonth();

        $attachment
            ->setAccount($financialMonth->getAccount())
            ->setFinancialMonth($financialMonth);

        // create a document for the invoice and link it to the transaction and financial month
        $document = $this->documentService->createFromInvoice($invoice)
            ->setFinancialMonth($financialMonth)
            ->setAttachment($attachment);
        $this->em->persist($document);

        $transaction
            ->addDocument($document)
            ->addDocument($attachment);

        $invoice->setPaymentDate($transaction->getBookingDate());

        // copy invoice files from the invoices folder to the accounting folder
        $this->documentService->copyInvoiceFilesToAccountingFolder($invoice);
    }

    public function unLinkDocument(?Transaction $transaction, Document $document): void
    {
        $transaction->removeDocument($document);

        if (null !== $invoice = $document->getInvoice()) {
            $invoice->setPaymentDate(null);
            $this->em->remove($document);

            if (null !== $attachment = $document->getAttachment()) {
                $transaction->removeDocument($attachment);
                $this->em->remove($attachment);
            }
        }

        // remove invoice files from the accounting folder
        $this->documentService->removeInvoiceAccountingFiles($invoice);
    }
}