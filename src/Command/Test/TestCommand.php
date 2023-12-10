<?php

namespace App\Command\Test;

use App\Entity\Invoice;
use CfdiUtils\Cfdi;
use CfdiUtils\SumasConceptos\SumasConceptos;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test',
    description: 'Add a short description for your command',
)]
class TestCommand extends Command
{
    private OutputInterface $output;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $invoices     = $this->em->getRepository(Invoice::class)->findBy(['series' => 'B'], null, 2, 0);
        $output->writeln(sprintf('Found %d invoices', count($invoices)));
        $cfdis = [];
        foreach ($invoices as $idx => $invoice) {
            $cfdiXml = $invoice->getCfdi();
            if (!is_string($cfdiXml)) {
                continue;
            }
            $cfdis[] = Cfdi::newFromString($cfdiXml);
        }
        $sumas = null;
        foreach ($cfdis as $cfdi) {
            if (null === $sumas) {
                $sumas = new SumasConceptos($cfdi->getNode());
            } else {
                //$sumas->addComprobante($cfdi->getNode());
            }
        }
        $comprobante = $cfdi->getQuickReader();
        $d           = ($comprobante->conceptos)()[0]['descripcion'];
        $i           = ((($comprobante->conceptos)()[0]->Impuestos)()['Traslados'])();
        $this->dump($comprobante());

        return Command::SUCCESS;

//            $cfdi  = Cfdi::newFromString($cfdiXml);
//            $sumas = new SumasConceptos($cfdi->getNode());
//            $a1    = $sumas->getRetenciones();
//            $a2    = $sumas->getImpuestosRetenidos();
//            $a3    = $sumas->getTraslados();
//            $a4    = $sumas->getImpuestosTrasladados();
//            $a     = 1;
        return Command::SUCCESS;
    }

    private function dump($reader, int $level = 0)
    {
        foreach ($reader as $item) {
            $prefix = str_repeat(' ', $level * 4);
            $this->output->writeln("$prefix. " . $item->__toString());
            foreach ($item->getAttributes() as $key => $value) {
                $this->output->writeln("$prefix  - $key : $value");
            }

            $this->dump($item(), $level + 1);
        }
    }
}
