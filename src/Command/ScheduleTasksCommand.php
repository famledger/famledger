<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

use App\Repository\InvoiceScheduleRepository;
use App\Repository\TenantRepository;
use App\Service\InvoiceTaskBuilder;

#[AsCommand(
    name: 'app:schedule-tasks',
    description: 'Schedules missing invoice tasks for the given tenant',
)]
class ScheduleTasksCommand extends Command
{
    public function __construct(
        private readonly InvoiceScheduleRepository $invoiceScheduleRepository,
        private readonly InvoiceTaskBuilder        $invoiceTaskBuilder,
        private readonly TenantRepository          $tenantRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant RFC for which to schedule tasks')
            ->addOption('force', null, InputOption::VALUE_REQUIRED, 'Tenant ID for which to schedule tasks', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $tenantRFC = $input->getOption('tenant');

        // Fetch tenant
        $tenant = $this->tenantRepository->findOneBy(['rfc' => $tenantRFC]);
        if (!$tenant) {
            $io->error(sprintf('Tenant with RFC %d not found.', $tenantRFC));

            return Command::FAILURE;
        }

        $io->info('Fetching schedules without tasks for this month...');

        // Fetch invoice schedules that are missing tasks
        $schedules = $this->invoiceScheduleRepository->findSchedulesWithoutCurrentTask($tenant);
        if (empty($schedules)) {
            $io->success('No missing tasks found. Everything is up-to-date.');

            return Command::SUCCESS;
        }

        if ($input->getOption('force') === false) {
            $io->warning(sprintf('Found %d missing schedules. Run this command with --force to create tasks.',
                count($schedules)));

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d missing schedules. Creating tasks...', count($schedules)));

        $createdTasks = 0;
        foreach ($schedules as $schedule) {
            $this->invoiceTaskBuilder->create($schedule);
            $createdTasks++;
            $io->success(sprintf('Created task for schedule ID %d', $schedule->getId()));
        }

        $io->success(sprintf('Successfully created %d tasks.', $createdTasks));

        return Command::SUCCESS;
    }
}