<?php

namespace App\Command\_sort_out;

use App\Service\DocumentDetector\DocumentDetectorRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'debug:document-detector',
    description: 'Dumps the document detectors registered with the DocumentDetectorRegistry',
)]
class DocumentDetectorDebugCommand extends Command
{
    public function __construct(
        private readonly DocumentDetectorRegistry $detectorRegistry
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("\nDocument Detectors");
        foreach ($this->detectorRegistry->getDebugConfig() as $class => $strategies) {
            $output->writeln("\n$class");
            foreach ($strategies as $strategy) {
                $output->writeln(' - ' . get_class($strategy));
            }
        }

        return Command::SUCCESS;
    }
}
