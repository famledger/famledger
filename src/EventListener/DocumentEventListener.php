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
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\InvoiceFileNamer;

#[AsEventListener(event: DocumentCreatedEvent::class, method: 'onDocumentCreated')]
#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated', priority: 0)]
class DocumentEventListener
{
    public function __construct(
        private readonly AttachmentRepository   $attachmentRepository,
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
     * @throws Exception
     */
    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $invoice = $event->getInvoice();

        if (null !== $attachment = $invoice->getAttachment()) {
            // if the invoice was canceled, this will only update the display name, the filename will stay the same
            $attachment->setDisplayFilename(InvoiceFileNamer::buildDocumentName($invoice, 'xml'));
        }

        if (null !== $document = $invoice->getDocument()) {
            // if the invoice was canceled, this will trigger the renaming of the corresponding accounting file
            $document->setFilename(InvoiceFileNamer::buildDocumentName($invoice, 'pdf'));
        }
    }
}