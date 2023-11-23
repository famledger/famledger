<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

            $qb->where($qb->expr()->gte('i.year', $qb->expr()->literal($currentYear - 1)));
        } else {
            $qb->where($qb->expr()->eq('i.year', $qb->expr()->literal($year)));
        }

        $query = $qb->getQuery()->getSQL();

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
}
