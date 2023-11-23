<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Customer;

/**
 * @extends ServiceEntityRepository<Customer>
 *
 * @method Customer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Customer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Customer[]    findAll()
 * @method Customer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function getOptions(): array
    {
        $rawOptions = $this->createQueryBuilder('c')
            ->select('c.rfc', 'c.name')
            ->getQuery()
            ->getArrayResult();

        $options = [];
        foreach ($rawOptions as $row) {
            $rfc           = $row['rfc'];
            $caption       = $rfc . '-' . $row['name'];
            $options[$rfc] = $caption;
        }
        ksort($options);

        return $options;
    }
}
