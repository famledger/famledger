<?php

namespace App\Service;

use App\Entity\TaxPayment;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

use App\Constant\DocumentType;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Exception\DocumentCreationException;
use App\Exception\StatementValidationFailedException;

class StatementService
{
    public function __construct(
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

    /**
     * @throws StatementValidationFailedException
     */
    public function validate(Statement $statement): void
    {
        $statement->setStatus(Statement::STATUS_PENDING);

        $this->validateTransactionConsistency($statement);

        // all transactions are valid, so we can
        // - establish the transaction type
        // - associate the transaction with a customer (if applicable)
        foreach ($statement->getTransactions() as $transaction) {
            // handle income transactions
            if ($transaction->getAmount() > 0) {
                // TODO: once the system is fully operational and legacy statements have been verified,
                //       we can probably remove the following validation and directly set the transaction type
                //       and customer of the first invoice
                // make sure all invoices are issued to the same customer
                $customer    = null;
                $customerIds = [];
                foreach ($transaction->getDocuments() as $document) {
                    if ($document->getType() === DocumentType::INCOME or $document->getType() === DocumentType::PAYMENT) {
                        $invoice       = $document->getInvoice();
                        $customer      = $invoice->getCustomer();
                        $customerIds[] = $customer->getId();
                    }
                }
                if (count(array_unique($customerIds)) > 1) {
                    throw new StatementValidationFailedException(sprintf('Transaction %d has invoice for multiple customers',
                        $transaction->getSequenceNo()
                    ));
                }
                // TODO: end of todo

                $transaction
                    ->setType(DocumentType::INCOME)
                    ->setCustomer($customer);
            }

            // TODO: handle expense transactions etc. they could potentially be linked to 'suppliers'
        }

        $statement->setStatus(Statement::STATUS_CONSOLIDATED);
    }

    /**
     * For each transaction, the updateConsolidationStatus() will check whether the documents associated
     * with the transaction are consistent with the transaction's amount.
     * This should actually be obsolete, since the method is called whenever a document is added/removed,
     * but we keep it for transactions that have been created from legacy files
     *
     * @throws StatementValidationFailedException
     */
    private function validateTransactionConsistency(Statement $statement): void
    {
        $unconsolidatedSequenceNumbers = [];
        foreach ($statement->getTransactions() as $transaction) {
            if ($transaction->getType() === DocumentType::ACCOUNT_STATEMENT) {
                continue;
            }
            $transaction->updateConsolidationStatus();
            if ($transaction->getStatus() !== Transaction::STATUS_CONSOLIDATED) {
                $unconsolidatedSequenceNumbers[] = $transaction->getSequenceNo();
            }
        }
        if (count($unconsolidatedSequenceNumbers) > 0) {
            throw new StatementValidationFailedException(sprintf('Transaction(s) %s could not be consolidated',
                implode(', ', $unconsolidatedSequenceNumbers)
            ));
        }
    }

    public function linkDocument(Transaction $transaction, ?Document $document): void
    {
        $transaction->addDocument($document);
        $document->setFinancialMonth($transaction->getStatement()->getFinancialMonth());
        if ($document instanceof TaxPayment) {
            $taxNotice = $document->getTaxNotice();
            $transaction->addDocument($taxNotice);
            $taxNotice->setFinancialMonth($transaction->getStatement()->getFinancialMonth()); // probably obsolete
        }
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
            if (null !== $attachment = $invoice->getAttachment()) {
                $this->documentService->removeDocument($attachment);
                $invoice->setAttachment(null);
            }
            $this->documentService->removeDocument($document);

            $invoice->setPaymentDate(null);
        } else {
            $transaction->removeDocument($document);
            if ($document instanceof TaxPayment) {
                $taxNotice = $document->getTaxNotice();
                $transaction->removeDocument($taxNotice);
            }
        }
    }
}