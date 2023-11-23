<?php

namespace App\Service\DocumentSpecs;

use App\Constant\DocumentType;
use App\Entity\Statement;

class StatementSpecs extends BaseDocumentSpecs
{
    private ?Statement $statement     = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::ACCOUNT_STATEMENT;
    }

    public function setStatement(?Statement $statement): self
    {
        $this->statement = $statement;

        return $this;
    }

    public function getStatement(): ?Statement
    {
        return $this->statement;
    }
}
