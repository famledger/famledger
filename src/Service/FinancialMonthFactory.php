<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\FinancialMonth;

class FinancialMonthFactory
{
    static public function create(Account $account, int $year, int $month): FinancialMonth
    {
        return (new FinancialMonth())
            ->setAccount($account)
            ->setYear($year)
            ->setMonth($month);
    }
}