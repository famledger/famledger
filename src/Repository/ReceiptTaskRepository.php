<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\ReceiptTask;

/**
 * @extends ServiceEntityRepository<ReceiptTask>
 *
 * @method ReceiptTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceiptTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceiptTask[]    findAll()
 * @method ReceiptTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceiptTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReceiptTask::class);
    }
}
