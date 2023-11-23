<?php

namespace App\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\InvoiceSchedule;
use App\Entity\InvoiceTask;

class InvoiceTaskBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function create(InvoiceSchedule $invoiceSchedule): InvoiceTask
    {
        $invoiceTask = (new InvoiceTask())
            ->setYear((new DateTime())->format('Y'))
            ->setMonth((new DateTime())->format('m'))
            ->setFirstDate(new DateTime()) // remove
            ->setProperty($invoiceSchedule->getProperty())
            ->setConcept($invoiceSchedule->getConcept());

        $invoiceSchedule->addInvoiceTask($invoiceTask);

        $this->em->persist($invoiceTask);
        $this->em->flush();

        return $invoiceTask;
    }
}