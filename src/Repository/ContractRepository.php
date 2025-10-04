<?php

namespace App\Repository;

use DateInterval;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

use App\Entity\Contract;

/**
 * @extends ServiceEntityRepository<Contract>
 *
 * @method Contract|null find($id, $lockMode = null, $lockVersion = null)
 * @method Contract|null findOneBy(array $criteria, array $orderBy = null)
 * @method Contract[]    findAll()
 * @method Contract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * Find contracts expiring within the specified number of days
     * @throws Exception
     */
    public function findExpiringSoon(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.endDate >= :today')
            ->andWhere('c.isActive = :active')
            ->setParameter('today', new DateTime())
            ->setParameter('active', true)
            ->orderBy('c.endDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active contracts for a customer
     */
    public function findActiveByCustomer($customer): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.customer = :customer')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.endDate >= :today')
            ->setParameter('customer', $customer)
            ->setParameter('active', true)
            ->setParameter('today', new DateTime())
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active contracts for a property
     */
    public function findActiveByProperty($property): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.property = :property')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.endDate >= :today')
            ->setParameter('property', $property)
            ->setParameter('active', true)
            ->setParameter('today', new DateTime())
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}