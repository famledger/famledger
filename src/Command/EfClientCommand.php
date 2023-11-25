<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Service\LiveModeContext;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use App\Entity\Tenant;
use App\Service\EFClient;
use App\Service\TenantContext;

#[AsCommand(
    name: 'app:ef-client',
    description: 'Performs different operations related to EFClient and tenants',
)]
class EfClientCommand extends Command
{
    public function __construct(
        private readonly EFClient               $efClient,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext          $tenantContext,
        private readonly LiveModeContext        $liveModeContext,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('operation', InputArgument::OPTIONAL,
                'Operation to perform: "test", "tenants", "list-invoices", or "show-invoice"')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'The RFC of the tenant')
            ->addOption('livemode', null, InputOption::VALUE_REQUIRED, 'execute request in either live or test mode')
            ->addOption('series', null, InputOption::VALUE_REQUIRED, '')
            ->addOption('number', null, InputOption::VALUE_REQUIRED, '')
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command performs different operations related to EFClient and tenants.

<comment>Operations:</comment>
- <info>test</info>: Tests the EFClient connection for a specific tenant. Requires the --tenant option.
- <info>tenants</info>: Returns a list of all tenants.
- <info>list-invoices</info>: Lists all invoices for a given series. Requires the --series option.
- <info>show-invoice</info>: Shows details of a specific invoice. Requires both --series and --number options.

<comment>Usage Examples:</comment>

To test a tenant with a specific RFC:
  <info>php %command.full_name% test --tenant=RFC123</info>

To list all tenants:
  <info>php %command.full_name% tenants</info>

To list invoices for a specific series:
  <info>php %command.full_name% list-invoices --series=S123</info>

To show a specific invoice:
  <info>php %command.full_name% show-invoice --series=S123 --number=456</info>

<comment>Arguments:</comment>
  <info>operation</info>: The operation to perform. Allowed values are <info>'test'</info>, <info>'tenants'</info>, <info>'list-invoices'</info>, and <info>'show-invoice'</info>.

<comment>Options:</comment>
  <info>--tenant</info>: The RFC of the tenant for which you want to test the connection. Required for all operations except <info>'tenants'</info>.
  <info>--series</info>: The series of the invoices. Required for <info>'list-invoices'</info> and <info>'show-invoice'</info> operations.
  <info>--number</info>: The number of the specific invoice. Required for <info>'show-invoice'</info> operation.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $operation = $input->getArgument('operation');
        $tenantRfc = $input->getOption('tenant');

        try {
            if ('tenants' !== $operation) {
                if (null === $tenantRfc) {
                    throw new Exception('Tenant RFC is required');
                }
                if (null === $tenant = $this->em->getRepository(Tenant::class)->findOneBy(['rfc' => $tenantRfc])) {
                    throw new Exception(sprintf('Tenant with RFC %s not found', $tenantRfc));
                }

                $this->tenantContext->setTenant($tenant);
            }
            $this->liveModeContext->setLiveMode($input->getOption('livemode'));

            switch($operation) {

                case 'list-invoices':
                    if (null === $series = $input->getOption('series')) {
                        throw new Exception('Series is required');
                    }
                    $response = $this->efClient->listInvoices($series);
                    $io->success('List of invoices for series ' . $series);
                    $io->note(json_encode($response, JSON_PRETTY_PRINT));

                    return Command::SUCCESS;

                case 'show-invoice':
                    if (null === $series = $input->getOption('series')) {
                        throw new Exception('Series is required');
                    }
                    if (null === $number = $input->getOption('number')) {
                        throw new Exception('Number is required');
                    }
                    $invoice  = $this->em->getRepository(Invoice::class)->findOneBy([
                        'series' => $series,
                        'number' => $number,
                    ]);
                    $response = $this->efClient->getInvoice($invoice);
                    $io->success('Invoice details');
                    $io->note(json_encode($response, JSON_PRETTY_PRINT));

                    return Command::SUCCESS;

                case 'test':
                    $tenant = $this->em->getRepository(Tenant::class)->findOneBy(['rfc' => $tenantRfc]);
                    if (null === $tenant) {
                        throw new Exception(sprintf('Tenant with RFC %s not found', $tenantRfc));
                    }

                    $this->tenantContext->setTenant($tenant);

                    if ($response = $this->efClient->testConnection()) {
                        $io->success('Your connection was successful.');
                        $io->note(json_encode($response, JSON_PRETTY_PRINT));

                        return Command::SUCCESS;
                    } else {
                        $io->error('Your connection was not unsuccessful.');

                        return Command::FAILURE;
                    }

                case 'tenants':
                    $tenants = $this->em->getRepository(Tenant::class)->findAll();

                    if (empty($tenants)) {
                        $io->warning('No tenants found.');

                        return Command::SUCCESS;
                    }

                    $tenantList = [];
                    foreach ($tenants as $tenant) {
                        $tenantList[] = [$tenant->getRfc(), $tenant->getName()];
                    }

                    $io->table(['RFC', 'Name'], $tenantList);

                    return Command::SUCCESS;

                default:
                    throw new Exception(sprintf('Operation "%s" not supported', $operation));
            }
        } catch (Throwable $e) {
            $io->error($e->getMessage());

        }

        return Command::FAILURE;
    }
}
