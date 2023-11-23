<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Constant\DocumentType;
use App\Entity\Account;
use App\Event\DocumentCreatedEvent;
use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Repository\AttachmentRepository;
use App\Service\DocumentService;
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\InvoiceFileNamer;

#[AsEventListener(event: DocumentCreatedEvent::class, method: 'onDocumentCreated')]
#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated', priority: 0)]
class DocumentEventListener
{
    public function __construct(
        private readonly AttachmentRepository   $attachmentRepository,
        private readonly DocumentService        $documentService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger
    ) {
    }

    /**
     * @throws Exception
     */
    public function onDocumentCreated(DocumentCreatedEvent $event): void
    {
        $document      = $event->getDocument();
        $documentSpecs = $event->getDocumentSpecs();
        $account       = $event->getAccount();

        if (DocumentType::ACCOUNT_STATEMENT !== $document->getType()) {
            return;
        }
        if (!$documentSpecs instanceof StatementSpecs) {
            return;
        }

        $statement        = $documentSpecs->getStatement();
        $statementAccount = $this->em->getRepository(Account::class)->findOneBy([
            'number' => $statement->getAccountNumber(),
        ]);
        if ($statementAccount->getNumber() !== $account->getNumber()) {
            $this->logger->error(sprintf('DOCUMENT_DETECTOR: Statement account number %s does not match account number %s',
                $statement->getAccountNumber(),
                $account->getNumber()
            ));
            throw new Exception('Statement account number does not match account number');
        }
        $statement
            ->setDocument($document)
            ->setFinancialMonth($document->getFinancialMonth())
            ->setAccount($account);

        $this->em->persist($statement);
    }

    /**
     * This method is called in 2 situations:
     * 1. when an invoice is created
     * 2. when an invoice is updated via a user initiated re-synchronization
     *
     * In the first case, the invoice has neither a Document entity (for the PDF) nor an Attachment entity (for the XML)
     * associated with it yet. In this case we create only the Attachment entity. The Document entity will be created
     * when the invoice is associated with a transaction.
     *
     * In the second case, the Attachment already exists and the Document entity may or may not exist.
     * Both have to be updated.
     *
     * @throws Exception
     */
    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $invoice = $event->getInvoice();

        // make sure an attachment exists for the invoice
        // association of attachment and document is not the responsibility of this listener
        if (null === $attachment = $this->attachmentRepository->findInvoiceAttachment($invoice)) {
            $attachment = $this->documentService->createAttachmentFromInvoice($invoice);
            $this->em->persist($attachment);
        } else {
            $attachment->setDisplayFilename(InvoiceFileNamer::getInvoiceDocumentName($invoice, 'xml'));
        }

        if (null !== $document = $invoice->getDocument()) {
            // if there is a document, there is also a corresponding file in the Financial Documents folder
            $this->documentService->updateFromInvoice($document);

            // the document associated with the invoice has been created when the invoice was associated with a transaction
            // at the same time, an attachment has been created for the invoice so either both or none of them exist
            if (null === $document->getAttachment()) {
                $document->setAttachment($attachment);
                $attachment
                    ->setAccount($document->getFinancialMonth()?->getAccount())
                    ->setTransaction($document->getTransaction());
            }
        }
    }
}