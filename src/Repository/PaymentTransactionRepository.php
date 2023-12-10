<?php

namespace App\Repository;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\PaymentTransaction;
use App\Entity\Transaction;
use App\Service\TenantContext;

/**
 * @extends ServiceEntityRepository<PaymentTransaction>
 *
 * @method PaymentTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentTransaction[]    findAll()
 * @method PaymentTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentTransactionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry                $registry,
        private readonly TenantContext $tenantContext,
    ) {
        parent::__construct($registry, PaymentTransaction::class);
    }

    /**
     * Get all payment transactions, grouped and/or filtered by year.
     * If no year is given, all transactions from the current and the last year are returned.
     */
    public function getHistory(?int $year = null): array
    {
        $currentYear = $year ?? (int)date('Y');

        // Calculate the first and last day of the year
        $startDate = new DateTime("{$currentYear}-01-01");
        $endDate   = new DateTime("{$currentYear}-12-31");
        if (null === $year) {
            // If no specific year is given, adjust the start date to 10 years ago
            $startDate->modify('-10 years');
        }
        $qb = $this->createQueryBuilder('t');
        $qb
            ->innerJoin('t.statement', 's')
            ->innerJoin('t.customer', 'c')
            ->leftJoin('t.documents', 'd')
            ->orderBy('t.bookingDate', 'desc');

        $qb
            ->where($qb->expr()->eq('s.tenant', $qb->expr()->literal($this->tenantContext->getTenant()->getId())))
            ->andWhere($qb->expr()->between('t.bookingDate', ':start', ':end'))
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);


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
