<?php

namespace App\Command\Fix;

use App\Entity\TaxNotice;
use App\Entity\TaxPayment;
use App\EventListener\DocumentListener;
use App\Service\ChecksumRegistry;
use App\Service\DocumentDetector\DocumentLoader;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Constant\DocumentSubType;
use App\Constant\DocumentType;
use App\Entity\Attachment;
use App\Entity\Document;

#[AsCommand(
    name: 'fix:tax-attachments',
    description: '',
)]
class FixTaxAttachmentsCommand extends Command
{
    public function __construct(
        private readonly ChecksumRegistry       $checksumRegistry,
        private readonly DocumentLoader         $documentLoader,
        private readonly DocumentService        $documentService,
        private readonly EntityManagerInterface $em,
        private readonly string                 $accountingFolder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->checksumRegistry->loadFolder($this->accountingFolder);
//            // make sure all tax payments
//            // - have the correct entity type
//            // - are located in the expected folder
//            // - have a capture line
//            $taxPayments = $this->getGroupedTaxDocuments(DocumentType::TAX->value);
//            foreach ($taxPayments as $taxPayment) {
//                $output->write(sprintf('Document %d: ... ', $taxPayment->getId()));
//                $filepath = $this->getFilepath($taxPayment);
//                if (null === $taxPayment->getCaptureLine()) {
//                    $specs = $this->documentLoader->load(
//                        $filepath,
//                        pathinfo($filepath, PATHINFO_EXTENSION),
//                        pathinfo($filepath, PATHINFO_BASENAME)
//                    );
//                    $taxPayment->setSpecs($specs->serialize());
//                    $this->em->flush();
//                    $output->writeln(sprintf('capture line has been updated: %s', $taxPayment->getCaptureLine()));
//                } else {
//                    $output->writeln('OK');
//                }
//            }
            // make sure all tax payments
            // - have the correct entity type
            // - are located in the expected folder
            // - have a capture line

            //==========================================================================================================
            // set the capture line on all tax notices
            $this->disableDocumentListener();

            $taxNotices = $this->getGroupedTaxDocuments(DocumentType::TAX_NOTICE->value);
            foreach ($taxNotices as $taxNotice) {
                if (!$taxNotice instanceof TaxNotice) {
                    throw new Exception('Invalid document type');
                }
                $output->write(sprintf('Document %d: ... ', $taxNotice->getId()));
                if (null === $taxNotice->getCaptureLine()) {
                    $filepath = $this->getFilepath($taxNotice);
                    $specs    = $this->documentLoader->load(
                        $filepath,
                        pathinfo($filepath, PATHINFO_EXTENSION),
                        pathinfo($filepath, PATHINFO_BASENAME)
                    );
                    $taxNotice->setSpecs($specs->serialize());

                    $this->em->flush();
                    $output->writeln(sprintf('capture line has been updated: %s', $taxNotice->getCaptureLine()));
                } else {
                    $output->writeln('OK');
                }
            }
//                    /** @var TaxPayment $taxPayment */
//                    $taxPayment = $this->em->getRepository(TaxPayment::class)->findOneBy(['captureLine' => $taxNotice->getCaptureLine()]);
//                    if (null === $taxPayment) {
//                        throw new Exception(sprintf(
//                            'Tax payment with capture line %s not found',
//                            $taxNotice->getCaptureLine()
//                        ));
//                    }
//                    $taxPayment->setTaxNotice($taxNotice);
//                    $taxNotice
//                        ->setTransaction($taxPayment->getTransaction())
//                        ->setFinancialMonth($taxPayment->getFinancialMonth())
//                        ->setSequenceNo($taxPayment->getSequenceNo());

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        $qb = $this->em->getRepository(Attachment::class)->createQueryBuilder('a');
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('a.type', $qb->expr()->literal(DocumentType::TAX_NOTICE->value)))
        );
        $attachments = $qb->getQuery()->getResult();
        $output->writeln(sprintf('Found %d tax notices', count($attachments)));

        $types = [];
        foreach ($attachments as $attachment) {
            /** @var TaxNotice $attachment */
            $specs = $attachment->getSpecs();
            $type  = $specs['type'] ?? 'unknown';
            if ('TaxNoticeSpecs' !== $type) {
                //$output->writeln(sprintf('Tax notice %d has type %s', $attachment->getId(), $type));
                if (null === $filepath = $this->documentService->getAccountingFilepath($attachment)) {
//                        $output->writeln(sprintf('Tax notice %d has no filepath', $attachment->getId()));
                }
                if (!file_exists($filepath)) {
//                        $output->writeln(sprintf('Tax notice %d has no file', $attachment->getId()));
                    continue;
                }
                $specs = $this->documentLoader->load(
                    $filepath,
                    pathinfo($filepath, PATHINFO_EXTENSION),
                    pathinfo($filepath, PATHINFO_BASENAME)
                );
                $attachment->setSpecs($specs->serialize());
                $output->writeln(sprintf('Tax notice %d has been updated %s', $attachment->getId(),
                    $attachment->getCaptureLine()));
                $a = 1;
            }
        }

        return Command::SUCCESS;

//            $this->em->flush();

        return Command::SUCCESS;
    }

    private function getDocumentsWithoutChecksum(): array
    {
        return $this->em->getRepository(Document::class)->findBy(['checksum' => null]);
    }

    /**
     * Returns documents grouped by type
     * - 'tax' tax payments
     * - 'tax-notice' tax notices (acuse)
     * - 'attachment' declarations
     */
    private function getGroupedTaxDocuments(?string $group = null): array
    {
        $qb = $this->em->getRepository(Document::class)->createQueryBuilder('d');
        $qb->where($qb->expr()->orX()
            ->add($qb->expr()->in('d.type', [DocumentType::TAX->value, DocumentType::TAX_NOTICE->value]))
            ->add($qb->expr()->eq('d.subType', $qb->expr()->literal(DocumentSubType::TAX_CALCULUS)))
        );

        $results = [];
        foreach ($qb->getQuery()->getResult() as $document) {
            /** @var Document $document */
            $type                    = $document->getType();
            $results[$type->value][] = $document;
        }

        return (null === $group) ? $results : $results[$group];
    }

    /**
     *
     * @throws Exception
     */
    private function getFilepath(mixed $taxNotice): string
    {
        if (!$taxNotice instanceof TaxNotice) {
            throw new Exception('Invalid document type');
        }
        if (null === $filepath = $this->documentService->getAccountingFilepath($taxNotice)) {
            throw new Exception(sprintf('Document %d has no filepath', $taxNotice->getId()));
        }
        if (file_exists($filepath)) {
            return $filepath;
        } else {
            // find the file by checksum
            if (null !== $filepath = $this->checksumRegistry->get($taxNotice->getChecksum())) {
                return $filepath;
            }
            throw new Exception(sprintf('Document %d has no file', $taxNotice->getId()));
        }
    }

    private function disableDocumentListener(): void
    {
        $eventManager = $this->em->getEventManager();
        foreach ($eventManager->getListeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof DocumentListener) {
                    $eventManager->removeEventListener([$eventName], $listener);
                }
            }
        }
    }
}
