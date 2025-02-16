<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\InvoiceSchedule;
use App\Entity\Tenant;

/**
 * @extends ServiceEntityRepository<InvoiceSchedule>
 *
 * @method InvoiceSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvoiceSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvoiceSchedule[]    findAll()
 * @method InvoiceSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceSchedule::class);
    }

    /**
     * Find all invoice schedules that do not have an invoice task for the current year/month
     */
    public function findSchedulesWithoutCurrentTask(Tenant $tenant): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.invoiceTasks', 't', Join::WITH, 't.year = :year AND t.month = :month')
            ->where('s.tenant = :tenant')
            ->andWhere('s.isActive = true')
            ->andWhere('t.id IS NULL') // Ensure no matching invoice task exists
            ->setParameter('tenant', $tenant)
            ->setParameter('year', (new DateTime())->format('Y'))
            ->setParameter('month', (new DateTime())->format('n'));

        return $qb->getQuery()->getResult();
    }
}
