<?php

namespace App\Service\DocumentSpecs;

use DateTime;

use App\Constant\DocumentType;

class ExpenseSpecs extends BaseDocumentSpecs
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::EXPENSE;
    }
}
