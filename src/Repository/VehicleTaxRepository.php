<?php

namespace App\Repository;

use App\Entity\VehicleTax;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleTax>
 *
 * @method VehicleTax|null find($id, $lockMode = null, $lockVersion = null)
 * @method VehicleTax|null findOneBy(array $criteria, array $orderBy = null)
 * @method VehicleTax[]    findAll()
 * @method VehicleTax[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VehicleTaxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleTax::class);
    }

//    /**
//     * @return VehicleTax[] Returns an array of VehicleTax objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('v.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?VehicleTax
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
