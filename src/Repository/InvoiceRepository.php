<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use App\Constant\DocumentType;
use App\Constant\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\Series;
use App\Entity\Tenant;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function getSubstitutionInvoices(Invoice $invoiceToCancel): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i');

        return $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('i.customer', $qb->expr()->literal($invoiceToCancel->getCustomer()->getId())))
            ->add($qb->expr()->eq('i.status', $qb->expr()->literal(InvoiceStatus::VIGENTE)))
            ->add($qb->expr()->neq('i.id', $qb->expr()->literal($invoiceToCancel->getId())))
            ->add($qb->expr()->isNull('i.substitutesInvoice'))
        );
    }

    /**
     * Get all invoices, grouped and/or filtered by year.
     * If no year is given, all invoices from the current and the last year are returned.
     */
    public function getHistory(array $activeSeries, ?int $year = null): array
    {
        $currentYear = $year ?? (int)date('Y');

        $qb = $this->createQueryBuilder('i');
        $qb
            ->leftJoin('i.property', 'p')
            ->leftJoin('i.document', 'd')
            ->leftJoin('d.transaction', 't')
            ->orderBy('i.year', 'desc')
            ->addOrderBy('i.month', 'desc')
            ->addOrderBy('i.number', 'desc');

        if (null === $year) {
            $qb->where($qb->expr()->gte('i.year', $qb->expr()->literal($currentYear - 10)));
        } else {
            $qb->where($qb->expr()->eq('i.year', $qb->expr()->literal($year)));
        }

        $invoices           = [];
//        $lastInvoiceNumbers = [];
        foreach ($qb->getQuery()->getResult() as $invoice) {
            /** @var Invoice $invoice */
            $series            = $invoice->getSeries();
            $year              = $invoice->getYear();
            $month             = $invoice->getMonth();
//            $lastInvoiceNumber = ($lastInvoiceNumbers[$series] ?? null);
//
//            // Detect and add missing invoices if needed
//            if (null !== $lastInvoiceNumber and in_array($series, $activeSeries)) {
//                for ($missingInvoice = $lastInvoiceNumber - 1; $missingInvoice > $invoice->getNumber(); $missingInvoice--) {
//                    $invoiceKey                           = $invoice->getSeries() . '-' . $missingInvoice;
//                    $invoices[$year][$month][$invoiceKey] = null;
//                }
//            }

            // Add the current invoice to the array
            $invoiceKey                           = $invoice->getSeries() . '-' . $invoice->getNumber();
            $invoices[$year][$month][$invoiceKey] = $invoice;

            // Update the lastInvoiceNumber for each series
//            $lastInvoiceNumbers[$series] = $invoice->getNumber();
        }

        // Sort the array by keys (invoice numbers)
        krsort($invoices);

        return $invoices;
    }

    public function findInvoicesWithoutDocuments(array $series): array
    {
        $qb = $this->createQueryBuilder('i');

        $qb
            ->leftJoin('i.document', 'd', 'WITH', "d.type = '" . DocumentType::INCOME->value . "'")
            ->where($qb->expr()->andX()
                // invoices for customer 'Servicios Empresariales de Alta Calidad' (5) were not created
                // consistently and could not be associated with corresponding transactions, so they are excluded
                //->add($qb->expr()->notIn('i.customer', [5, 7]))
                ->add($qb->expr()->isNull('d.id'))
                ->add($qb->expr()->in('i.series', $series))
                ->add($qb->expr()->orX()
                    //->add($qb->expr()->eq('i.status', $qb->expr()->literal(InvoiceStatus::VIGENTE)))
                    ->add($qb->expr()->gte('i.issueDate', $qb->expr()->literal('2022-09-01')))
                )
            )
            ->orderBy('i.customer', 'ASC')
            ->addOrderBy('i.series', 'ASC')
            ->addOrderBy('i.issueDate', 'ASC');

        $invoices = [];
        foreach ($qb->getQuery()->getResult() as $invoice) {
            /** @var Invoice $invoice */
            $invoices[$invoice->getCustomer()->getName()][] = $invoice;
        }

        return $invoices;
    }

    public function fetchIncomplete(Tenant $tenant, Series $series, ?bool $liveMode = true): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('i.isComplete', $qb->expr()->literal(false)))
            ->add($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())))
            ->add($qb->expr()->eq('i.series', $qb->expr()->literal($series->getCode())))
            ->add($qb->expr()->eq('i.liveMode', $qb->expr()->literal($liveMode)))
        )
            ->orderBy('i.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function getPropertyInvoicesQuery(mixed $property): QueryBuilder
    {
        return $this->createQueryBuilder('i')
            ->where('i.property = :property')
            ->setParameter('property', $property);
    }

    public function findExistingNumbersForSeries(Series $series, bool $liveMode): array
    {
        $tenant = $series->getTenant();
        $qb     = $this->createQueryBuilder('i');
        $qb->select('i.number')
            ->where($qb->expr()->andX()
                ->add($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())))
                ->add($qb->expr()->eq('i.series', $qb->expr()->literal($series->getCode())))
                ->add($qb->expr()->eq('i.liveMode', $qb->expr()->literal($liveMode)))
            )
            ->orderBy('i.id', 'DESC');

        return array_map(fn($invoice) => $invoice['number'], $qb->getQuery()->getResult());
    }

    public function findIncompleteInvoices(?Tenant $tenant = null)
    {
        $qb   = $this->createQueryBuilder('i');
        $andX = $qb->expr()->andX()
            ->add($qb->expr()->eq('i.isComplete', $qb->expr()->literal(false)));
        if (null !== $tenant) {
            $andX->add($qb->expr()->eq('i.tenant', $qb->expr()->literal($tenant->getId())));
        }
        $qb
            ->where($andX)
            ->orderBy('i.id', 'ASC');


        return $qb->getQuery()->getResult();
    }
}
