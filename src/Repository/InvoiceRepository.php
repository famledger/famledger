<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use App\Constant\InvoiceStatus;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Receipt;
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

    public function findInvoicesForCustomer(Customer $customer, ?Invoice $invoice)
    {
        $qb   = $this->createQueryBuilder('i');
        $andX = $qb->expr()->andX()
            ->add($qb->expr()->eq('i.customer', $qb->expr()->literal($customer->getId())));

        if ($invoice instanceof Receipt) {
            $andX->add($qb->expr()->isInstanceOf('i', Receipt::class));
        } else {
            $andX->add($qb->expr()->not($qb->expr()->isInstanceOf('i', Receipt::class)));
        }

        return $qb->where($andX)
            ->orderBy('i.issueDate', 'ASC')
            ->getQuery()
            ->getResult();
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
            $startDate = (new DateTime())->setDate($currentYear - 10, 1, 1);
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNotNull('i.year'),
                    $qb->expr()->andX(
                        $qb->expr()->isNull('i.year'),
                        $qb->expr()->gte('i.issueDate', ':startDate')
                    )
                )
            )->setParameter('startDate', $startDate);
        } else {
            $startOfYear = (new DateTime())->setDate($year, 1, 1);
            $endOfYear   = (new DateTime())->setDate($year, 12, 31);
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('i.year', $qb->expr()->literal($year)),
                    $qb->expr()->andX(
                        $qb->expr()->isNull('i.year'),
                        $qb->expr()->between('i.issueDate', ':startOfYear', ':endOfYear')
                    )
                )
            )->setParameter('startOfYear', $startOfYear)
                ->setParameter('endOfYear', $endOfYear);
        }

        $invoices = [];
        foreach ($qb->getQuery()->getResult() as $invoice) {
            /** @var Invoice $invoice */
            $invoiceYear  = $invoice->getYear() ?? $invoice->getIssueDate()->format('Y');
            $invoiceMonth = $invoice->getMonth() ?? (int)$invoice->getIssueDate()->format('m');

            // Add the current invoice to the array
            $invoiceKey                                         = $invoice->getSeries() . '-' . $invoice->getNumber();
            $invoices[$invoiceYear][$invoiceMonth][$invoiceKey] = $invoice;
        }

        // sort the years in descending order
        krsort($invoices);

        // iterate through each year
        foreach ($invoices as &$months) {
            // Sort the months in descending order for each year
            krsort($months);

            // Now, iterate through each month of the year
            foreach ($months as &$invoiceKeys) {
                // Sort the invoice keys in descending order for each month
                // If you need them in ascending order, you can use ksort() instead of krsort()
                krsort($invoiceKeys);
            }
        }
        unset($months); // break the reference with the last element
        unset($invoiceKeys); // break the reference with the last element

        return $invoices;
    }

    /**
     * @throws Exception
     */
    public function findInvoicesWithoutDocuments(array $series): array
    {
        // Step 1: Retrieve all invoices that are not associated with a document.
        $qb = $this->createQueryBuilder('i');

        $qb
            ->leftJoin('i.document', 'd')
            ->where($qb->expr()->andX()
                // invoices for customer 'Servicios Empresariales de Alta Calidad' (5) were not created
                // consistently and could not be associated with corresponding transactions, so they are excluded
                ->add($qb->expr()->notIn('i.customer', [5,16]))
                ->add($qb->expr()->isNull('d.id'))
                ->add($qb->expr()->in('i.series', $series))
                ->add($qb->expr()->neq('i.status', $qb->expr()->literal(InvoiceStatus::ANULADO)))
                ->add($qb->expr()->orX()
                    //->add($qb->expr()->eq('i.status', $qb->expr()->literal(InvoiceStatus::VIGENTE)))
                    ->add($qb->expr()->gte('i.issueDate', $qb->expr()->literal('2022-09-01')))
                )
            )
            ->orderBy('i.customer', 'ASC')
            ->addOrderBy('i.series', 'ASC')
            ->addOrderBy('i.issueDate', 'ASC');

        $unAssociatedInvoices = $qb->getQuery()->getResult();
        // get the customer ids
        $customerIds = array_map(fn($invoice) => $invoice->getCustomer()->getId(), $unAssociatedInvoices);
        // get all series codes that are not used for receipts


        $customerIds = join(',', array_unique($customerIds));
        // get the highest number per customer of any invoice considering only the payment date
        $query              = <<<EOT
SELECT i.customer_id, MAX(i.number) AS highestNumber
FROM invoice i
WHERE i.discr = 'invoice'
AND i.customer_id IN ($customerIds)
AND i.payment_date IS NOT NULL
GROUP BY i.customer_id
EOT;
        $highestPaidNumbers = [];
        if (!empty($customerIds)) {
            foreach ($this->getEntityManager()->getConnection()->executeQuery($query)->fetchAllAssociative() as $row) {
                $highestPaidNumbers[$row['customer_id']] = $row['highestNumber'];
            }
        }

        // Step 3: Mark unpaid invoices that have a lower number than the highest paid invoice number for the same customer.
        $invoicesByCustomer = [];
        foreach ($unAssociatedInvoices as $invoice) {
            /** @var Invoice $invoice */
            $customerName = $invoice->getCustomer()->getName();
            $customerId   = $invoice->getCustomer()->getId();
            // Assuming you've added a setUnPaid() method to the Invoice entity
            if (($highestPaidNumbers[$customerId] ?? 0) > $invoice->getNumber()
                and $invoice->getStatus() === InvoiceStatus::VIGENTE
                    and !$invoice instanceof Receipt
            ) {
                $invoice->setUnPaid(true);
            }

            // Group by customer name
            if (!isset($invoicesByCustomer[$customerName])) {
                $invoicesByCustomer[$customerName] = [];
            }
            $invoicesByCustomer[$customerName][] = $invoice;
        }

        return $invoicesByCustomer;
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

    public function findLatest(): array
    {
        $firstDayOfCurrentMonth  = new DateTime('today +1 day');
        $firstDayOfPreviousMonth = new DateTime('first day of last month');

        $qb = $this->createQueryBuilder('i');

        // Build the query to get all invoices from the current and the previous month
        $qb
            ->where($qb->expr()->andX()
                ->add($qb->expr()->gte('i.issueDate', $qb->expr()->literal($firstDayOfPreviousMonth->format('Y-m-d'))))
                ->add($qb->expr()->lt('i.issueDate', $qb->expr()->literal($firstDayOfCurrentMonth->format('Y-m-d'))))
                ->add($qb->expr()->isNull('i.document'))

            )
            ->orderBy('i.issueDate', 'DESC');

        // Execute the query and return the result
        return $qb->getQuery()->getResult();
    }

    public function getInvoiceYears(): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb
            ->select('distinct i.year')
            ->orderBy('i.year', 'DESC');

        return array_filter(array_map(function (array $record) {
            return (int)$record['year'];
        }, $qb->getQuery()->getResult()));
    }
}
