<?php

namespace App\Service\Accounting;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Account;
use App\Entity\FinancialMonth;
use App\Repository\FinancialMonthRepository;
use App\Service\FinancialMonthFactory;

class FinancialMonthService
{
    private array $financialMonthCache = [];

    public function __construct(
        private readonly EntityManagerInterface   $em,
        private readonly FinancialMonthRepository $financialMonthRepo,
    ) {
    }

    public function getOrCreateFinancialMonth(int $year, int $month, Account $account): FinancialMonth
    {
        $cacheKey = $this->buildCacheKey($year, $month, $account);

        if (isset($this->financialMonthCache[$cacheKey])) {
            return $this->financialMonthCache[$cacheKey];
        }

        $financialMonth = $this->financialMonthRepo->findOneBy([
            'year'    => $year,
            'month'   => $month,
            'account' => $account
        ]);

        if ($financialMonth === null) {
            $financialMonth = FinancialMonthFactory::create($account, $year, $month);
            $this->em->persist($financialMonth);
        }

        $this->financialMonthCache[$cacheKey] = $financialMonth;

        $financialMonth->setTenant($account->getTenant());

        return $financialMonth;
    }

    private function buildCacheKey(?int $year, ?int $month, ?Account $account): string
    {
        return "$year-$month-{$account->getNumber()}";
    }
}