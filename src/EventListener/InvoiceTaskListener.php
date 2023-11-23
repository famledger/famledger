<?php

namespace App\EventListener;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use IntlDateFormatter;

use App\Entity\Document;
use App\Entity\InvoiceTask;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class InvoiceTaskListener
{
    private function getOldEntity(PreUpdateEventArgs $args): Document
    {
        /** @var Document $oldDocument */
        $oldDocument = clone($args->getObject());
        foreach ($args->getEntityChangeSet() as $field => $changeSet) {
            $setter = 'set' . ucfirst($field);
            $oldDocument->$setter($args->getOldValue($field));
        }

        return $oldDocument;
    }

    /**
     * @throws Exception
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $invoiceTask = $args->getObject();
        if (!$invoiceTask instanceof InvoiceTask) {
            return;
        }
        $this->updateConcept($invoiceTask);
        // copy the template upon creation only
        $schedule = $invoiceTask->getInvoiceSchedule();
        $invoiceTask
            ->setSeries($schedule->getSeries())
            ->setTaxCategory($schedule->getTaxCategory())
            ->setInvoiceUsage($schedule->getInvoiceUsage())
            ->setPaymentMethod($schedule->getPaymentMethod())
            ->setPaymentForm($schedule->getPaymentForm())
            ->setAmount($schedule->getAmount())
            ->setInvoiceTemplate($schedule->getInvoiceTemplate())
            ->setCustomer($schedule->getCustomer());
    }

    /**
     * @throws Exception
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $invoiceTask = $args->getObject();
        if (!$invoiceTask instanceof InvoiceTask) {
            return;
        }
        $this->updateConcept($invoiceTask);
    }

    /**
     * @throws Exception
     */
    private function updateConcept(InvoiceTask $invoiceTask): void
    {
        $invoiceSchedule = $invoiceTask->getInvoiceSchedule();
        if (false === $invoiceSchedule->isMonthly()) {
            return;
        }

        $year      = $invoiceTask->getYear();
        $month     = $invoiceTask->getMonth();
        $firstDate = (new DateTime())
            ->setDate($year, $month, $invoiceSchedule->getMonthlyPaymentDay())
            ->setTime(0, 0, 0);

        $lastDate = clone $firstDate;
        $lastDate
            ->modify('+1 month')
            ->modify('-1 day');

        // Format the dates using IntlDateFormatter
        $formatter          = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $formattedFirstDate = $formatter->format($firstDate);
        $formattedLastDate  = $formatter->format($lastDate);

        $concept = sprintf('%s, Periodo: %s al %s',
            $invoiceSchedule->getConcept(),
            $formattedFirstDate,
            $formattedLastDate
        );
        $invoiceTask->setConcept($concept);
    }
}