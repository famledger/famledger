<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Constant\DocumentSubType;
use App\Constant\DocumentType;
use App\Entity\Account;
use App\Entity\Attachment;
use App\Entity\TaxNotice;
use App\Entity\TaxPayment;
use App\Event\DocumentCreatedEvent;
use App\Event\DocumentPreCreateEvent;
use App\Event\DocumentRebuildEvent;
use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\InvoiceFileNamer;

#[AsEventListener(event: DocumentCreatedEvent::class, method: 'onDocumentCreated')]
#[AsEventListener(event: DocumentPreCreateEvent::class, method: 'onDocumentPreCreate')]
#[AsEventListener(event: DocumentRebuildEvent::class, method: 'onDocumentRebuild')]
#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated', priority: 0)]
class DocumentEventListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger
    ) {
    }

    /**
     * Handles operations required for tax payments:
     * - associate the tax payment with the corresponding tax notice (throw exception if not found)
     * - update the tax payment year and month with the year and month of the tax notice
     * - lookup the tax calculus document for the same year and month
     * - set both the tax notice and the tax calculus document as related documents, so they can be handled by the caller
     *
     * @throws Exception
     */
    public function onDocumentPreCreate(DocumentPreCreateEvent $event): void
    {
        $document = $event->getDocument();

        // Tax payments must be associated with the tax notice they were paid for.
        // The year and month must be overwritten with the year and month of the tax notice.
        // The file name of the tax payment is being overwritten by associating it with the tax notice.
        // The tax calculus document is also being looked up by year/month, so it can be associated by the caller
        if ($document instanceof TaxPayment) {
            $taxNotice   = $this->handleTaxNotice($document);
            $taxCalculus = $this->getTaxCalculus($taxNotice);
            // make the tax notice available to the dispatcher of the DocumentPreCreateEvent
            $event->setRelatedDocuments(array_filter([$taxNotice, $taxCalculus]));
        }
    }

    /**
     * @throws Exception
     */
    public function onDocumentRebuild(DocumentRebuildEvent $event): void
    {
        $document = $event->getDocument();
        if ($document instanceof TaxPayment) {
            $this->handleTaxNotice($document);
        }
    }

    /**
     * @throws Exception
     */
    private function handleTaxNotice(TaxPayment $document): ?TaxNotice
    {
        // Tax payments must be associated with the tax notice they were paid for.
        // The year and month must be overwritten with the year and month of the tax notice.
        // The file name of the tax payment is being overwritten, by associating it with the tax notice.
        // lookup tax notice and associate it
        $repo = $this->em->getRepository(TaxNotice::class);
        if (null === $taxNotice = $repo->findOneBy([
                'type'        => DocumentType::TAX_NOTICE->value,
                'captureLine' => $document->getCaptureLine()
            ])) {
            throw new Exception(sprintf('No tax notice found for capture line: %s',
                $document->getCaptureLine()));
        }
        $document
            ->setTaxNotice($taxNotice)
            ->setYear($taxNotice->getYear())
            ->setMonth($taxNotice->getMonth());

        return $taxNotice;
    }


    /**
     * @throws NonUniqueResultException
     */
    private function getTaxCalculus(TaxNotice $taxNotice): ?Attachment
    {
        // lookup tax calculus for the same year and month
        $qb = $this->em->getRepository(Attachment::class)->createQueryBuilder('a');
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('a.type', $qb->expr()->literal(DocumentType::ATTACHMENT->value)))
            ->add($qb->expr()->eq('a.subType', $qb->expr()->literal(DocumentSubType::TAX_CALCULUS)))
            ->add($qb->expr()->eq('a.year', $qb->expr()->literal($taxNotice->getYear())))
            ->add($qb->expr()->eq('a.month', $qb->expr()->literal($taxNotice->getMonth())))
            ->add($qb->expr()->isNull('a.financialMonth'))
        );

        return $qb->getQuery()->getOneOrNullResult();
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
            $document->setFilename(InvoiceFileNamer::buildDocumentName($invoice));
        }
    }
}