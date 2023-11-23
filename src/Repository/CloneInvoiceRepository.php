<?php

namespace App\Repository;

use App\Entity\CloneInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CloneInvoice>
 *
 * @method CloneInvoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method CloneInvoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method CloneInvoice[]    findAll()
 * @method CloneInvoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CloneInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CloneInvoice::class);
    }

//    /**
//     * @return CloneInvoice[] Returns an array of CloneInvoice objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?CloneInvoice
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
