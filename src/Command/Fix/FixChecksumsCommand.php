<?php

namespace App\Command\Fix;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Entity\Document;

#[AsCommand(
    name: 'fix:checksums',
    description: 'Fixes the checksums of documents without a checksum (empty files)',
)]
class FixChecksumsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface  $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $affectedDocuments = $this->getDocumentsWithoutChecksum();
            $output->writeln(sprintf('Found %d files without checksum', count($affectedDocuments)));
            $output->writeln('Fixing checksum for documents ...');
            foreach ($affectedDocuments as $document) {
                $financialMonth = $document->getFinancialMonth();
                $output->write(sprintf('%s-%02d: %s',
                    $financialMonth->getYear(),
                    $financialMonth->getMonth(),
                    $document->getFilename())
                );
                // the checksum will be calculated in the DocumentListener
                $document->setUpdated(new \DateTime());
                $output->writeln(' fixed');
            }
            $this->em->flush();

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
