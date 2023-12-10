<?php

namespace App\Command\Fix;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Entity\Invoice;
use App\Service\InvoiceFileManager;

#[AsCommand(
    name: 'fix:invoice-cfdi',
    description: 'Stores the content of the CFDI in each invoice\'s cfdi field.'
)]
class FixInvoiceCfdiCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceFileManager     $invoiceFileManager,
    ) {
        parent::__construct();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invoices = $this->em->getRepository(Invoice::class)->findBy(['cfdi' => null]);
        $output->writeln(sprintf('Found %d invoices', count($invoices)));
        foreach ($invoices as $invoice) {
            try {
                $path = $this->invoiceFileManager->getXmlPath($invoice);
                $output->write(sprintf('%s-%s: %s',
                    $invoice->getSeries(),
                    $invoice->getNumber(),
                    $path
                ));
                $cfdiXml = file_get_contents($path);
                $invoice->setCfdi($cfdiXml);
                $output->writeln(' fixed');
            } catch (Exception $e) {
                $output->writeln($e->getMessage());
                continue;
            }
        }
        $this->em->flush();

        // find duplicates
        $freeInvoices     = [];
        $documentInvoices = [];
        foreach ($this->em->getRepository(Invoice::class)->findAll() as $invoice) {
            $key = $invoice->getSeries() . '-' . $invoice->getNumber() . '-' . $invoice->getTenant()->getId() . '-' . $invoice->getLiveMode();
            if (null === $invoice->getDocument()) {
                $freeInvoices[$key][] = $invoice;
            } else {
                if (isset($documentInvoices[$key])) {
                    throw new Exception(sprintf('Duplicate invoice with associated document: %s', $key));
                } else {
                    $documentInvoices[$key] = $invoice;
                }
            }
        }

        // $invoices contains potentially duplicate invoices
        // if there is an invoice associated with a document, we can delete the others
        // otherwise, we can delete all but one
        $removableInvoices = [];
        foreach ($freeInvoices as $key => $invoices) {
            if (isset($documentInvoices[$key])) {
                $removableInvoices = array_merge($removableInvoices, $invoices);
            } elseif (count($invoices) > 1) {
                $removableInvoices = array_merge($removableInvoices,
                    array_filter($invoices, function (Invoice $invoice) {
                        return null === $invoice->getData();
                    }));
            }
        }

        $output->writeln(sprintf('Found %d removable invoices', count($removableInvoices)));

        // there is an invoice with a corresponding document, we can delete the others
        foreach ($removableInvoices as $removableInvoice) {
            /** @var Invoice $removableInvoice */
            $output->writeln(sprintf('Removing %s: [%s]  %s-%s',
                $removableInvoice->getId(),
                $removableInvoice->getTenant()->getId(),
                $removableInvoice->getSeries(),
                $removableInvoice->getNumber()
            ));

            $pdfPath = $this->invoiceFileManager->getPdfPath($removableInvoice);
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            $xmlPath = $this->invoiceFileManager->getXmlPath($removableInvoice);
            if (file_exists($xmlPath)) {
                unlink($xmlPath);
            }
            $this->em->remove($removableInvoice);
            $this->em->flush();
        }

        return Command::SUCCESS;
    }
}