<?php

namespace App\Command\Fix;

use App\Constant\DocumentType;
use App\Entity\Attachment;
use App\Entity\Invoice;
use App\EventListener\DocumentListener;
use App\Exception\DocumentConversionException;
use App\Exception\DocumentDetectionException;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\ChecksumRegistry;
use App\Service\DocumentDetector\DocumentLoader;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\InvoiceSpecs;
use App\Service\InvoiceFileManager;
use App\Service\InvoiceFileNamer;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use App\Repository\DocumentRepository;
use App\Service\DocumentService;

#[AsCommand(
    name: 'fix:document-file-names',
    description: 'Add a short description for your command',
)]
class FixDocumentFileNamesCommand extends Command
{
    public function __construct(
        private readonly AccountingFolderManager $accountingFolderManager,
        private readonly ChecksumRegistry        $checksumRegistry,
        private readonly DocumentLoader          $documentLoader,
        private readonly DocumentRepository      $documentRepository,
        private readonly DocumentService         $documentService,
        private readonly InvoiceFileManager      $invoiceFileManager,
        private readonly EntityManagerInterface  $em,
        private readonly string                  $accountingFolder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->disableDocumentListener();

            $this->assertFilenameMatches($output);
            $this->removeInvoiceAttachments($output);
            $this->addInvoiceAttachments($output);

            return Command::SUCCESS;


            $this->checksumRegistry->loadFolder($this->accountingFolder);
            foreach ($this->checksumRegistry->getDuplicates() as $checksum => $files) {
                $mainFile = $this->checksumRegistry->get($checksum);
                $document = $this->documentRepository->findOneBy(['checksum' => $checksum]);
                $output->writeln($document->getType()->value);
                $output->writeln(sprintf("Detected %s", $mainFile));
                if ($document->getType() === DocumentType::ATTACHMENT) {
                    $attachment = $document;
                    $specs      = $attachment->getSpecs();
                    $tenantRfc  = $attachment->getTenant()->getRfc();
                    if (str_starts_with(($specs['suggestFilename'] ?? ''), $tenantRfc)) {
                        $output->writeln($specs['suggestFilename']);
                        // get the invoice number and series from the filename

                        // find the corresponding invoice document and associate it with the attachment

                    }
                }

                if ($document->getFilename() !== basename($mainFile)) {

                    $output->writeln(sprintf("document filename mismatch: %s - %s", $document->getFilename(),
                        basename($mainFile)));
                    continue;
                }

                foreach ($files as $cases) {
                    foreach ($cases as $subDir => $file) {
                        $output->writeln(sprintf("Deleting %s", $file));
                    }
                }
            }

            return Command::SUCCESS;
            $documents = $this->documentRepository->findAll();
            $output->writeln(sprintf("found %d documents", count($documents)));
            $matches = $mismatches = $sequenceMoves = $nameMoves = $errors = $checksumMatches = 0;
            foreach ($documents as $document) {
                if ($document->getTenant()->getId() !== 1) {
                    continue;
                }

                // skip matches
                $filename         = $document->getFilename();
                $documentFilePath = $this->documentService->getAccountingFilepath($document);
                if (is_file($documentFilePath)) {
                    $matches++;
                    continue;
                }

                // handle case where filename includes the sequence number
                if (preg_match('/^[0-9]{2} /', $filename)) {
                    $strippedFilename = preg_replace('/^[0-9]{2} /', '', $filename);
                    $document->setFilename($strippedFilename);
                    $strippedFilepath = $this->documentService->getAccountingFilepath($document);
                    if (is_file($strippedFilepath)) {
                        $sequenceMoves++;
                        $this->em->flush();
                        $output->writeln(sprintf('updating %s ->%s', $filename, $strippedFilename));
                        continue;
                    }
                }

                // handle case where file is not found by checksum lookup
                $checksum = $document->getChecksum();
                if (null === $foundFilePath = $this->checksumRegistry->get($checksum)) {
                    $mismatches++;
                    //$output->writeln($documentFilePath);
                    continue;
                }

                // handle case where file is found by checksum lookup but is in the wrong folder
                if (dirname($foundFilePath) == dirname($documentFilePath)) {
                    $output->writeln(sprintf('move %s ->%s', basename($foundFilePath), basename($documentFilePath)));
                    try {
                        rename($foundFilePath, $documentFilePath);
                    } catch (Throwable $e) {
                        $output->writeln($e->getMessage());
                    }
                    $checksumMatches++;
                    continue;
                }

                // handle case where file is found by checksum lookup and is in the right folder
                // should we update the document filename from the physical file name or vice versa?
                $nameMoves++;
                // $output->writeln(sprintf('move %s ->%s', $foundFilePath, $documentFilePath));
            }
            $output->writeln(sprintf("matches: %d, mismatches: %d, sequenceMoves: %d, nameMoves: %d, errors: %d, checksumMatches: %d",
                $matches,
                $mismatches,
                $sequenceMoves,
                $nameMoves,
                $errors,
                $checksumMatches
            ));

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
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

    /**
     * @throws Exception
     */
    private function assertFilenameMatches(OutputInterface $output)
    {
        // check whether all invoices have a corresponding PDF and XML file and that
        // the name stored in the invoice entity matches the actual file name
        $invoices = $this->em->getRepository(Invoice::class)->findAll();
        $output->writeln(sprintf("found %d invoices", count($invoices)));
        $matches          = 0;
        $missingDocuments = [];
        foreach ($invoices as $invoice) {
            $pdfPath = $this->invoiceFileManager->getPdfPath($invoice);
            if (!is_file($pdfPath)) {
                $output->writeln(sprintf("missing PDF file for invoice %d", $invoice->getId()));
                continue;
            }
            $xmlPath = $this->invoiceFileManager->getXmlPath($invoice);
            if (!is_file($xmlPath)) {
                $output->writeln(sprintf("missing XML file for invoice %d", $invoice->getId()));
                continue;
            }
            if (null === $document = $invoice->getDocument()) {
                $missingDocuments[] = $invoice->getInvoiceUid();
                continue;
            }
            if ($document->getFilename() == InvoiceFileNamer::buildDocumentName($invoice)) {
                $matches++;
            } else {
                $output->writeln(sprintf("document mismatch '%s' - '%s'",
                    $document->getFilename(),
                    InvoiceFileNamer::buildFileName($invoice, 'pdf')
                ));

                // fail the command if there are any filename mismatches
                throw new Exception('filename mismatch');
            }
        }

        $output->writeln(sprintf("invoice filename matches: %d", $matches));
        $output->writeln(sprintf("invoices without document: %d", count($missingDocuments)));
    }

    private function removeInvoiceAttachments(OutputInterface $output)
    {
        // remove all attachments for invoices issued by us and rebuild them
        $idx                    = 0;
        $missingFinancialMonths = [];
        $attachments            = $this->documentRepository->findBy(['type' => DocumentType::ATTACHMENT->value]);
        $output->writeln(sprintf("found %d attachments", count($attachments)));
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            // skip and notify attachments that are not associated with a financial month
            if (null === $attachment->getFinancialMonth()) {
                $missingFinancialMonths[] = $attachment->getId();
                continue;
            }
            $deleteFilepath = $this->accountingFolderManager->getAccountingFolderPath(
                    $attachment->getFinancialMonth(),
                    $attachment->isAttachment(),
                    true
                ) . '/' . $attachment->getFilename();
            if (is_file($deleteFilepath)) {
                //$output->writeln(sprintf('%3s: loading %s', $idx + 1, $deleteFilepath));
                try {
                    $specs = $this->documentLoader->load($deleteFilepath);
                } catch (Throwable) {
                    // we ignore loading error for attachments related to tuition payments
                    continue;   // Unknown children node 'iedu:instEducativas'
                }
                if (!$specs instanceof AttachmentSpecs) {
                    continue;
                }
                if (!empty($specs->getInvoiceNumber()) and !empty($specs->getInvoiceSeries())) {
                    $idx++;
                    $output->writeln(sprintf('%4d: Deleting attachment %s', $idx, $attachment->getId()));
                    if (null !== $document = $attachment->getParent()) {
                        $document->setAttachment(null);
                    }
                    $attachment->setInvoice(null);
                    $this->em->remove($attachment);
                    unlink($deleteFilepath);
                }
            }
        }
        $this->em->flush();
        $output->writeln(sprintf("missing financial months: %s", join(',', $missingFinancialMonths)));
    }

    /**
     * @throws Exception
     */
    private function addInvoiceAttachments(OutputInterface $output)
    {
        $invoiceDocuments = $this->documentRepository->findBy(['type' => DocumentType::INCOME->value]);
        $output->writeln(sprintf("found %d invoice documents", count($invoiceDocuments)));
        foreach ($invoiceDocuments as $invoiceDocument) {
            if (null === $transaction = $invoiceDocument->getTransaction()) {
                throw new Exception(sprintf('Invoice document %d has no transaction', $invoiceDocument->getId()));
            }

            /** @var Invoice $invoice */
            $invoice = $invoiceDocument->getInvoice();
            if (null === $invoice) {
                throw new Exception(sprintf('Invoice document %d has no invoice', $invoiceDocument->getId()));
            }

            $financialMonth = $invoiceDocument->getFinancialMonth();
            $attachment     = $this->documentService->createAttachmentFromInvoice($transaction, $invoice)
                ->setAccount($financialMonth->getAccount())
                ->setFinancialMonth($financialMonth)
                ->setTransaction($transaction)
                ->setInvoiceSeries($invoice->getSeries())
                ->setInvoiceNumber($invoice->getNumber())
                ->setTenant($invoice->getTenant()); // must be set to prevent the usage TenantContext
            $this->em->persist($attachment);

            $invoiceDocument->setAttachment($attachment);

            $output->writeln(sprintf("Created attachment for invoice %d", $invoice->getId()));
        }
        $this->em->flush();
    }
}