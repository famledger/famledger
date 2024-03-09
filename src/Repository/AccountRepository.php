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

    public function getOptions(string $accountType): array
    {
        $qb         = $this->createQueryBuilder('a');
        $rawOptions = $qb
            ->select('a.number, a.caption, a.isActive')
            ->where($qb->expr()->eq('a.type', $qb->expr()->literal($accountType)))
            ->getQuery()
            ->getArrayResult();

        $options         = [];
        $inactiveOptions = [];

        foreach ($rawOptions as $row) {
            $number  = $row['number'];
            $caption = $number . '-' . $row['caption'];

            if ($row['isActive']) {
                $options[$number] = $caption;
            } else {
                $inactiveOptions[$number] = $caption;
            }
        }

        ksort($options);
        ksort($inactiveOptions);

        // Merge inactive options as a subgroup
        if (!empty($inactiveOptions)) {
            $options['Inactive'] = $inactiveOptions;
        }

        return $options;
    }

    public function getIndexedByNumber(?bool $sorted = true): array
    {
        $qb = $this->createQueryBuilder('a');

        $qb->select('a')
            ->orderBy('a.number', 'ASC');

        $query = $qb->getQuery();

        $result = [];
        foreach ($query->getResult() as $account) {
            $result[$account->getNumber()] = $account;
        }
        if ($sorted) {
            uasort($result, function ($a, $b) {
                return strcmp($a->getCaption(), $b->getCaption());
            });
        }

        return $result;
    }

    public function findActive(): array
    {
        // TODO: implement active/inactive accounts
        // only return accounts that are not associated with a customer
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->in('a.type', ['bank-account', 'credit-card']))
            ->orderBy('a.caption', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
