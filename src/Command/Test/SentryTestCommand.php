<?php

namespace App\Command\Test;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sentry:test',
    description: 'Trigger errors to test Sentry integration',
)]
class SentryTestCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // the following code will test if monolog integration logs to sentry
        $this->logger->error('Thats a biggie.');

        // the following code will test if an uncaught exception logs to sentry
        throw new RuntimeException('Example exception 2.');
    }
}
