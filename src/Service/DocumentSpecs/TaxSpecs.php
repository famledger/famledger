<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;

class TaxSpecs extends ExpenseSpecs
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::TAX;
    }
}
