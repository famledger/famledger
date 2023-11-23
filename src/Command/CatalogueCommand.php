<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Constant\PaymentForm;
use App\Constant\PaymentMethod;

#[AsCommand(name: 'app:catalogue', description: 'Prints out the catalogue of fiscal values')]
class CatalogueCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('PaymentOptions');
        $output->writeln(json_encode(PaymentMethod::getOptions(), JSON_PRETTY_PRINT));

        $io->note('PaymentForms');
        $output->writeln(json_encode(PaymentForm::getOptions(), JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
