<?php

namespace App\Service\DocumentSpecs;

use DateTime;

use App\Constant\DocumentType;

class DonationSpecs extends ExpenseSpecs
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::DONATION;
    }
}
