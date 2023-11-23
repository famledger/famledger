<?php

namespace App\Repository;

use App\Entity\InvoiceTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceTask>
 *
 * @method InvoiceTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvoiceTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvoiceTask[]    findAll()
 * @method InvoiceTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceTask::class);
    }

//    /**
//     * @return InvoiceTask[] Returns an array of InvoiceTask objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?InvoiceTask
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
