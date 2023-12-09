<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\EmailLog;

/**
 * @extends ServiceEntityRepository<EmailLog>
 *
 * @method EmailLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailLog[]    findAll()
 * @method EmailLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLog::class);
    }
}
