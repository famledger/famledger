<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\InvoiceTask;

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
}
