<?php

namespace App\Command\Integrity;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Repository\FinancialMonthRepository;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AccountingFolderManager;

#[AsCommand(
    name: 'integrity:accounting-folder',
    description: 'Add a short description for your command',
)]
class IntegrityAccountingFolderCommand extends Command
{
    public function __construct(
        private readonly FinancialMonthRepository $financialMonthRepository,
        private readonly AccountingFolderManager  $accountingFolderManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // fetch all documents
        $financialMonths = $this->financialMonthRepository->findAll();
        $output->writeln('Found ' . count($financialMonths) . ' financial months');
        foreach ($financialMonths as $financialMonth) {
            $account = $financialMonth->getAccount();
            $output->writeln(sprintf('%s %s-%s',
                $account->getNumber(),
                $financialMonth->getYear(),
                $financialMonth->getMonth()
            ));
            foreach ($financialMonth->getDocuments() as $document) {
                $path     = $this->accountingFolderManager
                    ->getAccountingFolderPath($financialMonth, $document->isAttachment());
                $filename = $document->getFilename();
                if (preg_match('/^\d\d /', $filename)) {
                    $filename = substr($filename, 3);
                    $document->setFilename($filename);
                }
                $filename2 = AccountingDocumentService::composeFilename($document);
                if (is_file($path . '/' . $filename) and !is_file($path . '/' . $filename2)) {
                    $io->writeln("  - [{$document->getId()}] renaming $filename to $filename2");
                    //rename($path . '/' . $filename, $path . '/' . $filename2);
                }
                if (!is_file($path . '/' . $filename) and !is_file($path . '/' . $filename2)) {
                    $io->writeln("  - neither $filename nor $filename2 exist");
                }
                if (is_file($path . '/' . $filename2)) {
                    //$io->writeln("  - file $filename2 exists");
                }
            }
            $a = 1;
        }
        // determine the accounting folder for each document
        $financialMonth = $document->getFinancialMonth();


        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
