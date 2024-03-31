<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Constant\AccountType;
use App\Constant\DocumentType;
use App\Entity\Transaction;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Get all invoices, grouped and/or filtered by year.
     * If no year is given, all invoices from the current and the last year are returned.
     */
    public function getHistory(array $activeSeries, ?int $year = null): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->select('t, d, i, c')
            ->innerJoin('t.customer', 'c')
            ->leftJoin('t.documents', 'd')
            ->leftJoin('d.invoice', 'i')
            ->where($qb->expr()->isNotNull('t.customer'))
            ->orderBy('t.bookingDate', 'desc')
            ->addOrderBy('i.number', 'desc');

        if (null === $year) {
            $currentYear = (int)date('Y');

            $qb->andWhere($qb->expr()->gte('i.year', $qb->expr()->literal($currentYear - 1)));
        } else {
            $qb->andWhere($qb->expr()->eq('i.year', $qb->expr()->literal($year)));
        }

        $transactions = [];
        foreach ($qb->getQuery()->getResult() as $transaction) {
            /** @var Transaction $transaction */
            $year  = $transaction->getBookingDate()->format('Y');
            $month = $transaction->getBookingDate()->format('m');

            $transactions[$year][$month][] = $transaction;
        }

        krsort($transactions);

        return $transactions;
    }

    /**
     * @throws \Exception
     */
    public function getExpenseHistory(?int $year): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->select('t, s, a')
            ->innerJoin('t.statement', 's')
            ->innerJoin('s.account', 'a')
            ->orderBy('t.bookingDate', 'asc');

        $andX = $qb->expr()->andX()
            ->add($qb->expr()->eq('t.type', $qb->expr()->literal(DocumentType::EXPENSE->value)))
            ->add($qb->expr()->eq('a.type', $qb->expr()->literal(AccountType::CREDIT_CARD)));

        if (null === $year) {
            $currentYear = (int)date('Y');
            $startDate   = new DateTime($currentYear - 1 . '-01-01 00:00:00');
            $andX->add($qb->expr()->gte('t.bookingDate', $qb->expr()->literal($startDate->format('Y-m-d H:i:s'))));
        } else {
            $startDate = new DateTime($year . '-01-01 00:00:00');
            $endDate   = new DateTime($year . '-12-31 23:59:59');
            $andX
                ->add($qb->expr()->gte('t.bookingDate', $qb->expr()->literal($startDate->format('Y-m-d H:i:s'))))
                ->add($qb->expr()->lte('t.bookingDate', $qb->expr()->literal($endDate->format('Y-m-d H:i:s'))));
        }

        $transactions = [];
        foreach ($qb->where($andX)->getQuery()->getResult() as $transaction) {
            /** @var Transaction $transaction */
            $year                  = $transaction->getBookingDate()->format('Y');
            $transactions[$year][] = $transaction;
        }

        krsort($transactions);

        return $transactions;
    }


    public function getExpensesYears(): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('t.bookingDate')
            ->orderBy('t.bookingDate', 'DESC');

        return array_unique(array_map(function ($record) {
            return (int)$record['bookingDate']->format('Y');
        }, $qb->getQuery()->getResult()));
    }
}
