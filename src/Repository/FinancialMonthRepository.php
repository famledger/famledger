<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Account;
use App\Entity\FinancialMonth;

/**
 * @extends ServiceEntityRepository<FinancialMonth>
 *
 * @method FinancialMonth|null find($id, $lockMode = null, $lockVersion = null)
 * @method FinancialMonth|null findOneBy(array $criteria, array $orderBy = null)
 * @method FinancialMonth[]    findAll()
 * @method FinancialMonth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FinancialMonthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialMonth::class);
    }

    public function findExisting(int $year, int $month, Account $account): ?FinancialMonth
    {
        $qb = $this->createQueryBuilder('f');
        $qb
            ->where($qb->expr()->andX()
                ->add($qb->expr()->eq('f.year', $qb->expr()->literal($year)))
                ->add($qb->expr()->eq('f.month', $qb->expr()->literal($month)))
                ->add($qb->expr()->eq('f.account', $qb->expr()->literal($account->getId())))
            );

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByDateRange(Account $account, DateTime $startDate, DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('f');

        $startYear  = (int)$startDate->format('Y');
        $startMonth = (int)$startDate->format('m');
        $endYear    = (int)$endDate->format('Y');
        $endMonth   = (int)$endDate->format('m');

        // Add the account condition to the query
        $qb->andWhere($qb->expr()->eq('f.account', $qb->expr()->literal($account->getId())));

        // Handling the year boundaries
        if ($startYear === $endYear) {
            // Same year, straightforward range
            $condition = $qb->expr()->andX()
                ->add($qb->expr()->eq('f.year', $qb->expr()->literal($startYear)))
                ->add($qb->expr()->gte('f.month', $qb->expr()->literal($startMonth)))
                ->add($qb->expr()->lte('f.month', $qb->expr()->literal($endMonth)));
        } else {
            // Spanning multiple years
            // (f.year = :startYear AND f.month >= :startMonth) OR
            // (f.year > :startYear AND f.year < :endYear) OR
            // (f.year = :endYear AND f.month <= :endMonth)
            $condition = $qb->expr()->orX()
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->eq('f.year', $qb->expr()->literal($startYear)))
                    ->add($qb->expr()->gte('f.month', $qb->expr()->literal($startMonth)))
                )
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->gt('f.year', $qb->expr()->literal($startYear)))
                    ->add($qb->expr()->lt('f.year', $qb->expr()->literal($endYear)))
                )
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->eq('f.year', $qb->expr()->literal($endYear)))
                    ->add($qb->expr()->lte('f.month', $qb->expr()->literal($endMonth)))
                );
        }

        return $qb->andWhere($condition)->getQuery()->getResult();
    }
}
