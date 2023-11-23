<?php

namespace App\Repository;

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
}
