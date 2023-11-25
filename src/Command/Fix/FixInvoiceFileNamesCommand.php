<?php

namespace App\Command\Fix;

use App\Entity\Attachment;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Series;
use App\Entity\Tenant;
use App\EventListener\DocumentListener;
use App\Exception\EfClientException;
use App\Exception\EfStatusException;
use App\Exception\InvoiceException;
use App\Repository\AttachmentRepository;
use App\Repository\DocumentRepository;
use App\Repository\SeriesRepository;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AttachmentFolderManager;
use App\Service\ChecksumHelper;
use App\Service\DocumentService;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\EFClient;
use App\Service\Invoice\InvoiceSynchronizer;
use App\Service\InvoiceSyncService;
use App\Service\LiveModeContext;
use App\Service\TenantContext;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Repository\InvoiceRepository;
use App\Repository\TenantRepository;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\InvoiceFileManager;
use App\Service\InvoiceFileNamer;
use Symfony\Component\Finder\Finder;
use Throwable;

#[AsCommand(
    name: 'fix:invoice-file-names',
    description: 'Add a short description for your command',
)]
class FixInvoiceFileNamesCommand extends Command
{
    private OutputInterface $output;

    private array $invoices = [];

    public function __construct(
        private readonly AccountingDocumentService $accountingDocumentService,
        private readonly AttachmentRepository      $attachmentRepository,
        private readonly DocumentService           $documentService,
        private readonly EFClient                  $efClient,
        private readonly EntityManagerInterface    $em,
        private readonly InvoiceFileManager        $invoiceFileManager,
        private readonly InvoiceRepository         $invoiceRepository,
        private readonly InvoiceSyncService        $invoiceSyncService,
        private readonly InvoiceSynchronizer       $invoiceSynchronizer,
        private readonly LiveModeContext           $liveModeContext,
        private readonly SeriesRepository          $seriesRepository,
        private readonly TenantContext             $tenantContext,
        private readonly TenantRepository          $tenantRepository,
        private readonly string                    $invoicesFolder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        try {
            $this->disableDocumentListener();

            // for all documents that are associated with an invoice
            // - set the Document filename in accordance with the InvoiceFileNamer
            // - rename the accounting file to match the new filename
            // - re-fetch the invoice details and PDF/XML files
            foreach ($this->tenantRepository->findAll() as $tenant) {
                $invoiceDocuments = $this->getInvoiceDocuments($tenant);
                $this->updateInvoiceDocuments($invoiceDocuments);
            }

            // some invoices might not be associated with a document, but we want to have the PDF/XML files
            // we check for the existence of the PDF/XML files and fetch them if they are missing
            $invoices = $this->em->getRepository(Invoice::class)->findAll();
            foreach ($invoices as $invoice) {
                $path = $this->invoiceFileManager->getPdfPath($invoice);
                if (!is_file($path)) {
                    $this->output->writeln(sprintf("fetching %s", $path));
                    try {
                        $this->invoiceSynchronizer->syncInvoiceDetails($invoice);
                    } catch (EfStatusException $e) {
                        $output->writeln($e->getMessage());
                    }
                }
                $path = $this->invoiceFileManager->getXmlPath($invoice);
                if (!is_file($path)) {
                    $this->output->writeln(sprintf("missing %s", $path));
                }
            }

            // remove duplicate attachments
            $query      = "SELECT group_concat(id) FROM document WHERE type='attachment' GROUP BY checksum HAVING count(*) > 1";
            $ids        = explode(',', join(',', $this->em->getConnection()
                ->executeQuery($query)
                ->fetchFirstColumn()
            ));
            $duplicates = [];
            foreach ($this->em->getRepository(Attachment::class)->findBy(['id' => $ids]) as $attachment) {
                $attachmentId                                          = $attachment->getId();
                $duplicates[$attachment->getChecksum()][$attachmentId] = $attachment;
                $output->writeln(sprintf("%s: %s", $attachmentId, $attachment->getFilename()));
                if (str_ends_with($attachment->getFilename(), ' copy.xml')
                    or str_ends_with($attachment->getFilename(), ' 2.xml')
                    or (false === str_starts_with($attachment->getFilename(), 'MOPM670510J8A'))
                ) {
                    $output->writeln(sprintf("  - deleting %s", $attachment->getFilename()));
                    $this->accountingDocumentService->deleteDocument($attachment);
                    $this->em->flush();
                    unset($duplicates[$attachment->getChecksum()][$attachmentId]);
                }
            }

            // modify all invoice attachments (XML)
            // - set the display filename in accordance with the InvoiceFileNamer
            // - rename the accounting file to match the new filename
            // - set the invoice number and series in the specs
            $attachments = $this->em->getRepository(Attachment::class)->findAll();
            $n           = 0;
            foreach ($attachments as $attachment) {
                $suggestedFilename = $attachment->getFilename();
                if (preg_match('/^(MIJO620503Q60|MOPM670510J8A)-([0-9]{8})-([A-Z])([0-9]+).xml/',
                    $suggestedFilename,
                    $matches
                )) {
                    $n++;
                    $output->writeln(sprintf("%3d: %s", $n, $suggestedFilename));

                    $rfc           = $matches[1];
                    $invoiceNumber = (int)$matches[4];
                    $invoiceSeries = $matches[3];
                    // update the specs to include the invoice series and number
                    $specs = new AttachmentSpecs($attachment->getSpecs());
                    $specs
                        ->setInvoiceSeries($invoiceSeries)
                        ->setInvoiceNumber($invoiceNumber);
                    $attachment->setSpecs($specs->serialize());
                    // find the corresponding invoice document, set the display filename and attach the attachment
                    if (null != $invoice = $this->getInvoiceForAttachment($invoiceNumber, $invoiceSeries, $rfc)) {
                        $filename = InvoiceFileNamer::buildDocumentName($invoice, 'xml');
                        $attachment
                            ->setFilename($filename)
                            ->setDisplayFilename($filename)
                            ->setInvoice($invoice);
                        if (null !== $document = $invoice->getDocument()) {
                            $document->setAttachment($attachment);
                            if (null !== $transaction = $document->getTransaction()) {
                                $transaction->addDocument($attachment);
                            }
                        }
                    }
                }
            }
            $output->write('flushing ... ');
            $this->em->flush();
            $output->writeln('done');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @throws EfClientException
     */
    private function updateInvoiceDocuments(array $invoiceDocuments)
    {
        foreach ($invoiceDocuments as $invoice) {
            // update the filename of each document using the InvoiceFileNamer
            // when the document is persisted, the accounting file will be renamed
            // to match the new filename if it has changed
            $filename = InvoiceFileNamer::buildDocumentName($invoice);
            $document = $invoice->getDocument();
            $document->setFilename($filename);

            $this->output->writeln(sprintf("  - %s", $filename));

            // fetch the details and PDF/XML files for the invoice
            // the filenames in the invoices folder will match the ones in the accounting folder
            $this->invoiceSynchronizer->syncInvoiceDetails($invoice);
            $this->em->flush();
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

    private function getInvoiceDocuments(Tenant $tenant): array
    {
        $qb = $this->em->getRepository(Invoice::class)->createQueryBuilder('i');
        $qb
            ->select('i,d')
            ->innerJoin('i.document', 'd')
            ->where($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())))
            ->orderBy('i.series', 'ASC')
            ->addOrderBy('i.number', 'ASC');

        return $qb->getQuery()->getResult();
    }

    private function getInvoiceForAttachment(int $invoiceNumber, string $invoiceSeries, string $rfc): ?Invoice
    {
        if (empty($this->invoices)) {
            foreach ($this->em->getRepository(Tenant::class)->findAll() as $tenant) {
                $tenantRfc = $tenant->getRfc();
                $qb        = $this->em->getRepository(Invoice::class)->createQueryBuilder('i');
                $qb
                    ->select('i,d')
                    ->leftJoin('i.document', 'd')
                    ->where($qb->expr()->andX()
                        ->add($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())))
                        ->add($qb->expr()->eq('i.liveMode', $qb->expr()->literal(true)))
                    );

                foreach ($qb->getQuery()->getResult() as $invoice) {
                    /** @var Invoice $invoice */
                    $key = $invoice->getNumber() . '-' . $invoice->getSeries();

                    $this->invoices[$tenantRfc][$key] = $invoice;
                }
            }
        }
        $key = $invoiceNumber . '-' . $invoiceSeries;

        return $this->invoices[$rfc][$key] ?? null;
    }


    private function getInvoiceFiles(Tenant $tenant): array
    {
        $invoices = [];
        $finder   = new Finder();
        $finder->files()->in($this->invoicesFolder); // Look for files in the specified directory
        foreach ($finder as $file) {
            $invoiceFilename = $file->getFilename();
            // split up the file name into its parts
            $pattern = '/^(_)?(\d+)[- ]([A-Za-z]+)/';
            if (!preg_match($pattern, $invoiceFilename, $matches)) {
                continue;
            }

            $liveMode = $matches[1] !== '_';
            $number   = $matches[2];
            $series   = $matches[3];

            // Use the live mode to differentiate between live and test invoices.
            $key = $series . '-' . $number . '-' . (int)$liveMode;

            // Ensure the array for the key is initialized.
            if (!isset($invoices[$key])) {
                $invoices[$key] = [
                    'pdf' => null,
                    'xml' => null
                ];
            }
            $extension                  = pathinfo($invoiceFilename, PATHINFO_EXTENSION);
            $invoices[$key][$extension] = $file->getPathname();
        }

        return $invoices;
    }

    /**
     * Fetches the invoice files (PDF and XML) for all active series and stores them locally.
     *
     * @throws EfClientException
     */
    private function fetchActiveSeries(Tenant $tenant): array
    {
        // The LivemodeFilter and TenantFilter are only enabled in the web context,
        // so we can expect to get series un-filtered here.
        $seriesByTenant = $this->seriesRepository->getActiveSeriesByTenant($tenant);
        // we have grouped the series by tenant, because the series code is unique per tenant but could be duplicated
        // across tenants, this allows us to display the tenant RFC in combination with the series code in the report
        $report = [];
        foreach ($seriesByTenant as $rfc => $tenantSeries) {
            foreach ($tenantSeries as $series) {
                $modes = 'api' === strtolower($series->getSource()) ? [true, false] : [true];
                foreach ($modes as $liveMode) {
                    $reportKey = sprintf('%s-%s-%s',
                        $rfc,
                        $series->getCode(),
                        $liveMode ? 'live' : 'test'
                    );

                    // update all invoices for series->code / series->tenant / liveMode
                    $report[$reportKey] = $this->synchronizeSeries($series, $liveMode);
                }
            }
        }

        return $report;
    }

    /**
     * Fetches all invoices files (PDF and XML) for the given series and live mode and stores them locally.
     * The invoices are not persisted to the database.
     * Returns the number of invoices added.
     *
     * @throws EfClientException
     * @throws Exception
     */
    private function synchronizeSeries(Series $series, bool $liveMode): int
    {
        // before calling the EF API, set the livemode and tenant contexts from the series
        $this->liveModeContext->setLiveMode($liveMode);
        $this->tenantContext->setTenant($series->getTenant());

        $invoices = $this->efClient->listInvoices($series->getCode(), new DateTime('2017-01-01'));

        $countAdded = 0;
        foreach (($invoices['Comprobantes'] ?? []) as $invoiceData) {
            // create invoices in memory only and don't persist them, so we can use the InvoiceFileManager
            // to fetch the invoice files and store them locally
            $invoice = $this->invoiceSyncService->initInvoiceFromData(new Invoice(), $invoiceData, $liveMode);
            $this->invoiceSyncService->updateInvoice($invoice);
            try {
                $this->invoiceFileManager->fetchOrUpdateInvoiceFiles($invoice);
            } catch (InvoiceException $e) {
                $this->output->writeln($e->getMessage());
                continue;
            }
            $countAdded++;
        }

        return $countAdded;
    }

    private function updateDocuments(array $invoiceFiles)
    {
        // determine the expected file name for each invoice


        // scan the invoices folder
        // for each file, extract the invoice number and series e.g. from '5-A--00.pdf'
        // and output the filename and the expected file name using the FileNamer::getInvoiceFilename() method
        foreach ($invoiceFiles as $key => $files) {

            $this->output->write(sprintf("%-10s | ", $key));
            [$series, $number, $liveMode] = explode('-', $key);
            if (0 == $liveMode) {
                $this->output->writeln("skipping test invoice");
                continue;
            }

            $invoice = $this->invoiceRepository->findOneBy([
                'number'   => $number,
                'series'   => $series,
                'liveMode' => $liveMode
            ]);
            if (null === $invoice) {
                $this->output->writeln("no invoice, skipping");
                continue;
            }

            if (null === $document = $invoice->getDocument()) {
                $this->output->writeln("no document, skipping");
                continue;
            }

            // the actual file name displayed in the statement
            $documentFilename = $document->getFilename();
            // the expected file name
            $documentDisplayName = InvoiceFileNamer::buildDocumentName($invoice);
            // the full path the accounting file is expected to be found at
            try {
                $accountingFilePath = $this->documentService->getDocumentPath($document);

                if (preg_match('/^[0-9]{2} /', $documentFilename) and is_file($accountingFilePath)) {
                    $this->output->writeln("must fix");
                    $this->output->writeln(sprintf("  - %s\n  - %s",
                        $documentFilename,
                        $documentDisplayName
                    ));
                    // the accounting file will be renamed
                    $document->setFilename($documentDisplayName);
                    $this->em->flush();
                }

                // find the attachment by checksum
                $xmlFilePath = $files['xml'];
                $checksum    = ChecksumHelper::get(file_get_contents($xmlFilePath));
                if (null === $attachment = $this->attachmentRepository->findOneBy(['checksum' => $checksum])) {
                    $this->output->writeln("no attachment, skipping");
                    continue;
                }
                /** @var Attachment $attachment */
                $attachment
                    ->setParent($document)
                    ->setTransaction($document->getTransaction());
                $this->em->flush();
                echo "\n";
            } catch (Exception $e) {
                $this->output->writeln("no accounting file");
                continue;
            }
//            $output->writeln(sprintf("%s\n  - %s\n  - %s",
//                $invoiceFilename,
//                $document->getFilename(),
//                $accountingFileName
//            ));
        }
    }
}
