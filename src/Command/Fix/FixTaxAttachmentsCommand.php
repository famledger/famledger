<?php

namespace App\Command\Fix;

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
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $qb = $this->em->getRepository(Attachment::class)->createQueryBuilder('a');
            $qb->where($qb->expr()->andX()
                ->add($qb->expr()->eq('a.type', $qb->expr()->literal(DocumentType::ATTACHMENT->value)))
                ->add($qb->expr()->eq('a.subType', $qb->expr()->literal(DocumentSubType::TAX_NOTICE)))
            );
            $notices = $qb->getQuery()->getResult();
            $output->writeln(sprintf('Found %d tax notices', count($notices)));
            // group notices by their tenant id and amount
            $groupedNotices = [];
            foreach ($notices as $notice) {
                $tenantId = $notice->getTenant()->getId();
                $amount   = $notice->getAmount();
                if (!isset($groupedNotices[$tenantId])) {
                    $groupedNotices[$tenantId] = [];
                }
                if (!isset($groupedNotices[$tenantId][$amount])) {
                    $groupedNotices[$tenantId][$amount] = [];
                }
                $groupedNotices[$tenantId][$amount][] = $notice;
            }

//            $this->em->flush();

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getDocumentsWithoutChecksum(): array
    {
        return $this->em->getRepository(Document::class)->findBy(['checksum' => null]);
    }
}
