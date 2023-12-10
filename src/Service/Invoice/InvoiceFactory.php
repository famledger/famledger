<?php

namespace App\Service\Invoice;

use Exception;

use App\Constant\SeriesType;
use App\Entity\Invoice;
use App\Entity\Receipt;
use App\Entity\Series;

class InvoiceFactory
{
    /**
     * @throws Exception
     */
    static public function create(Series $series): Invoice|Receipt
    {
        return match ($series->getType()) {
            SeriesType::INVOICE => new Invoice(),
            SeriesType::PAYMENT => new Receipt(),
            default             => throw new Exception('Invalid series type'),
        };
    }
}