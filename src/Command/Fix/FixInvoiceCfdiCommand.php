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

        return Command::SUCCESS;
    }
}
