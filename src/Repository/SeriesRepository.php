<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Series;
use App\Entity\Tenant;

/**
 * @extends ServiceEntityRepository<Series>
 *
 * @method Series|null find($id, $lockMode = null, $lockVersion = null)
 * @method Series|null findOneBy(array $criteria, array $orderBy = null)
 * @method Series[]    findAll()
 * @method Series[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Series::class);
    }

    public function getActiveSeries(): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where($qb->expr()->eq('s.isActive', $qb->expr()->literal(true)));
        $series = [];
        foreach ($qb->getQuery()->getResult() as $serie) {
            $series[] = $serie->getCode();
        }

        return $series;
    }

    public function getActiveSeriesByTenant(?Tenant $tenant = null): array
    {
        $qb = $this->createQueryBuilder('s');

        $qb->where($qb->expr()->eq('s.isActive', $qb->expr()->literal(true)));
        if (null !== $tenant) {
            $qb->andWhere($qb->expr()->eq('s.tenant', $qb->expr()->literal($tenant->getId())));
        }

        $seriesByTenant = [];
        foreach ($qb->getQuery()->getResult() as $series) {
            /** @var Series $series */
            $seriesByTenant[$series->getTenant()->getRfc()][$series->getCode()] = $series;
        }
        ksort($seriesByTenant);

        return $seriesByTenant;
    }
}
