<?php

namespace App\Command\_sort_out;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Property;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'init:create-clients',
    description: 'Add a short description for your command',
)]
class InitCreateClientsCommand extends Command
{
    public function __construct(
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
        $propertyMap = [
            'Renta de la oficina #6'                                    => 'TU6',
            'Renta de la oficina #8'                                    => 'TY8',
            'Renta mensual de departamento situado en Circ. Copan #23'  => 'CRA',
            'Renta mensual de departamento situado en Calle Oyamel #14' => 'OYA',
            'Renta de la oficina #216'                                  => 'PAB',
            'Renta mensual de departamento situado en Depto E'          => 'ALB',
        ];

        $matchStrings = [];
        $customerMap  = [];
        $invoices     = $this->em->getRepository(Invoice::class)->findAll();
        foreach ($invoices as $invoice) {
            $description                = $invoice->getDescription();
            $matchString                = substr($description, 0, strpos($description, ','));
            $matchStrings[$matchString] = $matchString;

            $rfc               = $invoice->getRecipientRFC();
            $customerMap[$rfc] = $invoice->getRecipientName();
        }

        $properties = [];
        foreach ($this->em->getRepository(Property::class)->findAll() as $property) {
            $properties[$property->getSlug()] = $property;
        }

        foreach ($matchStrings as $matchString) {
            $slug = $propertyMap[$matchString];
            if (isset($properties[$slug])) {
                continue;
            }

            $property = (new Property())
                ->setSlug($slug)
                ->setIsActive(true)
                ->setCaption($slug);
            $this->em->persist($property);

            $properties[$slug] = $property;
        }
        $this->em->flush();

        $customers = [];
        foreach ($customerMap as $rfc => $name) {
            $customer = (new Customer())
                ->setRfc($rfc)
                ->setName($name);
            $this->em->persist($customer);

            $customers[$rfc] = $customer;
        }
        $this->em->flush();

        foreach ($invoices as $invoice) {
            $description = $invoice->getDescription();
            $matchString = substr($description, 0, strpos($description, ','));
            $slug        = $propertyMap[$matchString];
            $monthYear   = $this->getMonthYearFromDescription($description);
            $customer    = $customers[$invoice->getRecipientRFC()];
            $invoice
                ->setCustomer($customer)
                ->setProperty($properties[$slug])
                ->setYear($monthYear['year'])
                ->setMonth($monthYear['month']);
        }
        $this->em->flush();

        return Command::SUCCESS;
    }

    private function getMonthYearFromDescription(string $description): array
    {
        preg_match('/Periodo:.*?(\d+ de [a-zA-Z]+ \d{4})/', $description, $matches);

        $monthMapping = [
            'enero'      => 1,
            'febrero'    => 2,
            'marzo'      => 3,
            'abril'      => 4,
            'mayo'       => 5,
            'junio'      => 6,
            'julio'      => 7,
            'agosto'     => 8,
            'septiembre' => 9,
            'octubre'    => 10,
            'noviembre'  => 11,
            'diciembre'  => 12
        ];

        $dateComponents = explode(' ', $matches[1]);
        $monthName      = strtolower($dateComponents[2]);
        $year           = $dateComponents[3];

        $month = $monthMapping[$monthName] ?? 0;

        return ['month' => (int)$month, 'year' => (int)$year];
    }
}
