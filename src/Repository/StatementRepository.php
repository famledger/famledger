<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Statement;

/**
 * @extends ServiceEntityRepository<Statement>
 *
 * @method Statement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statement[]    findAll()
 * @method Statement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statement::class);
    }

    public function findByDateRange(DateTime $startDate, DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('s');

        $startYear  = (int)$startDate->format('Y');
        $startMonth = (int)$startDate->format('m');
        $endYear    = (int)$endDate->format('Y');
        $endMonth   = (int)$endDate->format('m');

        // Handling the year boundaries
        if ($startYear === $endYear) {
            // Same year, straightforward range
            $condition = $qb->expr()->andX()
                ->add($qb->expr()->eq('s.year', $qb->expr()->literal($startYear)))
                ->add($qb->expr()->gte('s.month', $qb->expr()->literal($startMonth)))
                ->add($qb->expr()->lte('s.month', $qb->expr()->literal($endMonth)));
        } else {
            // Spanning multiple years
            // (s.year = :startYear AND s.month >= :startMonth) OR
            // (s.year > :startYear AND s.year < :endYear) OR
            // (s.year = :endYear AND s.month <= :endMonth)')
            $condition = $qb->expr()->orX()
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->eq('s.year', $qb->expr()->literal($startYear)))
                    ->add($qb->expr()->gte('s.month', $qb->expr()->literal($startMonth)))
                )
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->gt('s.year', $qb->expr()->literal($endYear)))
                    ->add($qb->expr()->lt('s.year', $qb->expr()->literal($endYear)))
                )
                ->add($qb->expr()->andX()
                    ->add($qb->expr()->eq('s.year', $qb->expr()->literal($endYear)))
                    ->add($qb->expr()->lte('s.month', $qb->expr()->literal($endMonth)))
                );
        }

        return $qb->where($condition)->getQuery()->getResult();
    }
}
