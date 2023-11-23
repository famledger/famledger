<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\EDoc;

/**
 * @extends ServiceEntityRepository<EDoc>
 *
 * @method EDoc|null find($id, $lockMode = null, $lockVersion = null)
 * @method EDoc|null findOneBy(array $criteria, array $orderBy = null)
 * @method EDoc[]    findAll()
 * @method EDoc[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EDocRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EDoc::class);
    }

    public function findForEntity(mixed $getInstance): array
    {
        return [];
    }
}
