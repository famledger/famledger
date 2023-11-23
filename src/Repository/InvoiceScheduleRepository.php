<?php

namespace App\Repository;

use App\Entity\InvoiceSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceSchedule>
 *
 * @method InvoiceSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvoiceSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvoiceSchedule[]    findAll()
 * @method InvoiceSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceSchedule::class);
    }
}
