<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Throwable;

use App\Entity\Account;
use App\Service\Accounting\FinancialMonthService;
use App\Service\LegacyFileService;
use App\Service\StatementService;

#[AsCommand(
    name: 'init:import-statements',
    description: 'Add a short description for your command',
)]
class InitialImportCommand extends Command
{
    private array $configBusiness = [
        '1447391412' => '/Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Mayela Monroy - Arrendamiento/Expediente Contable',
        '1447302029' => '/Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Jorgo Miridis - Bodas/Expediente Contable',
    ];
    private array $configPrivate  = [
        '1447271220' => '/Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Bancomer Edos de Cuenta/Gasto Familiar',
        '1447253494' => '/Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Bancomer Edos de Cuenta/Mayela Monroy Personal',
        '2885949823' => '/Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Bancomer Edos de Cuenta/Jorgo Miridis Nomina'
    ];

    public function __construct(
        private readonly LegacyFileService      $legacyFileService,
        private readonly FinancialMonthService  $financialMonthService,
        private readonly StatementService       $statementService,
        private readonly EntityManagerInterface $em
    ) {
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

        try {
            foreach (['business', 'private'] as $type) {

                $statementPaths = $this->getStatementPaths($type);
                foreach ($statementPaths[$type] as $accountNumber => $paths) {
                    $io->writeln(sprintf('Account %s: %4d folders found', $accountNumber, count($paths)));
                    $account = $this->em->getRepository(Account::class)->findOneBy(['number' => $accountNumber]);
                    sort($paths);
                    foreach ($paths as $sourcePath) {
                        [$year, $month] = $this->getYearMonthFromPath($sourcePath, $type);
                        if (null === $year or null === $month) {
                            $io->error(sprintf('Could not get year and month from path "%s"', $sourcePath));
                            continue;
                        }

                        // create a FinancialMonth object which is required by the AccountingFolderManager
                        // to determine the target paths
                        $io->writeln(sprintf('- %s-%02d %s', $year, $month, basename($sourcePath)));
                        $financialMonth = $this->financialMonthService
                            ->getOrCreateFinancialMonth($year, $month, $account)
                            ->setTenant($account->getTenant());

                        if ('business' === $type) {
                            // copy all files recursively from the legacy folder to the new folder structure
                            $this->legacyFileService->copyLegacyFolder($financialMonth, dirname($sourcePath));

                            $this->legacyFileService->syncDocuments($financialMonth);

                            if (null !== $statement = $financialMonth->getStatement()) {
                                $this->statementService->validate($statement);
                            }
                        } else {
                            $this->legacyFileService->importLegacyStatement($sourcePath, $financialMonth);
                        }

                        $this->em->flush();
                    }
                    $this->em->clear();
                }
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function getYearMonthFromPath(mixed $path, string $type): array
    {
        if ('business' === $type) {
            // get year and month last path segment
            // 2022-08 Agosto
            $segments     = explode('/', $path);
            $relativePath = implode('/', array_slice($segments, -2));

            return preg_match('/^(\d{4})-(\d{2}) /', $relativePath, $matches)
                ? [$matches[1], $matches[2]]
                : [null, null];
        }
        if ('private' === $type) {
            // get year and month from filename
            // Estado de Cuenta Familia 1447271220 2022-08.pdf
            $segments     = explode('/', $path);
            $relativePath = implode('/', array_slice($segments, -1));

            return preg_match('/(\d{4})-(\d{2})\.pdf$/', $relativePath, $matches)
                ? [$matches[1], $matches[2]]
                : [null, null];
        }
        throw new Exception(sprintf('Type "%s" not supported', $type));
    }

    // /Users/jorgo/Library/CloudStorage/GoogleDrive-jorgo@miridis.com/My Drive/_2022/_Common/Contabilidad/Documentos Fiscales/Mayela Monroy - Arrendamiento/Expediente Contable/_Archivo Ejercicios Anteriores/_2012/2012-11 Noviembre/00 Estado de Cuenta 966029 2012-11.pdf
    private function getStatementPaths(string $type): array
    {
        $statements = [
            'business' => [],
            'private'  => []
        ];
        if ($type === 'business') {
            foreach ($this->configBusiness as $accountNumber => $path) {
                $finder = new Finder();
                $finder->files()
                    ->in($path)
                    ->exclude('_Archivo Ejercicios Anteriores')
                    ->exclude('/2015/')
                    ->name('/^00 .*\.pdf$/');

                foreach ($finder as $file) {
                    $statements['business'][$accountNumber][] = $file->getRealPath();
                }
            }
        }

        if ($type === 'private') {
            foreach ($this->configPrivate as $accountNumber => $path) {
                $finder = new Finder();
                $finder->files()
                    ->in($path)
                    ->name('/^.*\.pdf$/');

                foreach ($finder as $file) {
                    $statements['private'][$accountNumber][] = $file->getRealPath();
                }
            }
        }

        return $statements;
    }
}
