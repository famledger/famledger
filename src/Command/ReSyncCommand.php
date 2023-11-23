<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use App\Entity\Account;
use App\Service\Accounting\FinancialMonthService;
use App\Service\LegacyFileService;

#[AsCommand(
    name: 'app:re-sync',
    description: 'Add a short description for your command',
)]
class ReSyncCommand extends Command
{
    public function __construct(
        private readonly LegacyFileService      $legacyFileService,
        private readonly FinancialMonthService  $financialMonthService,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('account-number', null, InputOption::VALUE_REQUIRED, 'the account number')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'the year to be synced')
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'the mont to be synced');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // get extract options into variables
            $accountNumber = $input->getOption('account-number');
            $year          = $input->getOption('year');
            $month         = $input->getOption('month');

            $account        = $this->em->getRepository(Account::class)->findOneBy(['number' => $accountNumber]);
            $financialMonth = $this->financialMonthService
                ->getOrCreateFinancialMonth($year, $month, $account)
                ->setTenant($account->getTenant());

            $this->legacyFileService->syncDocuments($financialMonth);

//            $this->em->flush();

            return Command::SUCCESS;
        } catch (Throwable) {
            return Command::FAILURE;
        }
    }
}