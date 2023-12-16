<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use App\Entity\Invoice;
use App\Entity\Tenant;
use App\EventListener\DocumentListener;
use App\Exception\EfClientException;
use App\Exception\EfStatusException;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceFileNamer;
use App\Service\Invoice\InvoiceSynchronizer;

#[AsCommand(
    name: 'app:invoices',
    description: 'Add a short description for your command',
)]
class InvoicesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceSynchronizer    $invoiceSynchronizer,
        private readonly InvoiceRepository      $invoiceRepo,
        private readonly LoggerInterface        $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('operation', InputArgument::OPTIONAL, 'Either fetch-single or fetch-all')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'the RFC of the tenant')
            ->addOption('number', null, InputOption::VALUE_REQUIRED, 'the invoice number, only used with fetch-single')
            ->addOption('series', null, InputOption::VALUE_REQUIRED, 'the series of the invoice, use with fetch-single')
            ->addOption('livemode', null, InputOption::VALUE_REQUIRED,
                'whether to fetch in live mode or not, use with fetch-single')
            ->addOption('with-details', null, InputOption::VALUE_NONE,
                'fetch invoice details, only used with fetch-single')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command performs various operations related to invoices.

Here are some example usages:

  <info>php %command.full_name% fetch-single --tenant=TENANT_RFC --number=INVOICE_NUMBER --series=INVOICE_SERIES</info>
  Fetches a single invoice based on the given tenant RFC, invoice number, and series.
  
  <info>php %command.full_name% fetch-all --tenant=TENANT_RFC</info>
  Fetches all invoices for the specified tenant RFC.
  
  <info>php %command.full_name% fetch-details --tenant=TENANT_RFC --series=INVOICE_SERIES</info>
  Fetches detailed information for invoices in the specified series for the given tenant RFC.

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $operation = $input->getArgument('operation');
            if (null === $operation) {
                throw new Exception('Operation is required');
            }

            switch($operation) {

                case 'sync-all':
                    $tenant    = null;
                    $tenantRfc = $input->getOption('tenant');
                    if (null !== $tenantRfc) {
                        $tenant = $this->em->getRepository(Tenant::class)->findOneBy(['rfc' => $tenantRfc]);
                    }
                    $io->info('Fetching new invoices for active series');
                    $report = $this->invoiceSynchronizer->fetchActiveSeries($tenant);
                    foreach ($report as $key => $countProcessed) {
                        $io->writeln(sprintf('%s: %d', $key, $countProcessed));
                    }
                    $this->em->flush();

                    // fetch all invoice documents and update their filename according to the current naming policy
                    $io->info('Updating document filenames');
                    $eventManager = $this->em->getEventManager();
                    foreach ($eventManager->getListeners() as $eventName => $listeners) {
                        foreach ($listeners as $listener) {
                            if ($listener instanceof DocumentListener) {
                                $eventManager->removeEventListener([$eventName], $listener);
                            }
                        }
                    }

                    $qb = $this->em->getRepository(Invoice::class)->createQueryBuilder('i');
                    $qb
                        ->select('i,d')
                        ->innerJoin('i.document', 'd')
                        ->where($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())))
                        ->orderBy('i.series', 'ASC')
                        ->addOrderBy('i.number', 'ASC');

                    $invoices = $qb->getQuery()->getResult();
                    foreach ($invoices as $invoice) {
                        /** @var Invoice $invoice */
                        $invoice->getDocument()->setFilename(InvoiceFileNamer::buildDocumentName($invoice));
                    }
                    $this->em->flush();

                    $io->info('Fetching details for new invoices');
                    $incompleteInvoices = $this->invoiceRepo->findIncompleteInvoices($tenant);

                    foreach ($incompleteInvoices as $invoice) {
                        if ('Anulado' === $invoice->getStatus()) {
                            continue;
                        }
                        try {
                            $output->writeln(sprintf('Fetching details for %s %s ... ',
                                $invoice->getTenant()->getRfc(),
                                $invoice
                            ));
                            $this->invoiceSynchronizer->syncInvoiceDetails($invoice);
                            $this->em->flush();
                        } catch (Throwable $e) {
                            $this->logger->error($e->getMessage());
                        }
                    }
                    break;

                case 'fetch-single':
                    // TODO: remove this case once it is clear that it is no longer needed
                    $io->warning('Updating a single invoice is not supported anymore, use the admin interface instead.');

                    return Command::SUCCESS;

                case 'fetch-details':
                    // TODO: remove this case once it is clear that it is no longer needed
                    $io->warning(<<<EOT
Fetching all invoice details for a tenant is not supported anymore.
It is handled now by the operation 'sync-all'.
EOT
                    );

                    return Command::SUCCESS;

                default:
                    throw new Exception('Invalid operation');
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
//
//    /**
//     * @throws EfClientException
//     */
//    private function fetchActiveSeries(?Tenant $tenant = null): array
//    {
//        // The LivemodeFilter and TenantFilter are only enabled in the web context,
//        // so we can expect to get series un-filtered here.
//        $seriesByTenant = $this->seriesRepository->getActiveSeriesByTenant($tenant);
//
//        // we have grouped the series by tenant, because the series code is unique per tenant but could be duplicated
//        // across tenants, this allows us to display the tenant RFC in combination with the series code in the report
//        $report = [];
//        foreach ($seriesByTenant as $rfc => $tenantSeries) {
//            foreach ($tenantSeries as $series) {
//                $modes = 'api' === strtolower($series->getSource()) ? [true, false] : [true];
//                foreach ($modes as $liveMode) {
//                    $reportKey = sprintf('%s-%s-%s',
//                        $rfc,
//                        $series->getCode(),
//                        $liveMode ? 'live' : 'test'
//                    );
//                    // update all invoices for series->code / series->tenant / liveMode
//                    $report[$reportKey] = $this->invoiceSynchronizer->synchronizeSeries($series, $liveMode);
//                }
//            }
//        }
//
//        return $report;
//    }
}
