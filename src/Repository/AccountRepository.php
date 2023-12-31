<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Account;

/**
 * @extends ServiceEntityRepository<Account>
 *
 * @method Account|null find($id, $lockMode = null, $lockVersion = null)
 * @method Account|null findOneBy(array $criteria, array $orderBy = null)
 * @method Account[]    findAll()
 * @method Account[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function getIndexedByNumber(?bool $sorted = true): array
    {
        $qb = $this->createQueryBuilder('a');

        $qb->select('a')
            ->orderBy('a.number', 'ASC');

        $query = $qb->getQuery();

        $result   = [];
        foreach ($query->getResult() as $account) {
            $result[$account->getNumber()]   = $account;
        }
        if ($sorted) {
            uasort($result, function ($a, $b) {
                return strcmp($a->getCaption(), $b->getCaption());
            });
        }

        return $result;
    }
}
