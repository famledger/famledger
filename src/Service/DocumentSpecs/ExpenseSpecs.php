<?php

namespace App\Service\DocumentSpecs;

use DateTime;

use App\Constant\DocumentType;

class ExpenseSpecs extends BaseDocumentSpecs
{
    private ?DateTime $issueDate = null;

    public function getDocumentType(): DocumentType
    {
        return DocumentType::EXPENSE;
    }

    public function getIssueDate(): ?DateTime
    {
        return $this->issueDate;
    }

    public function setIssueDate(?DateTime $issueDate): self
    {
        $this->issueDate = $issueDate;

        return $this;
    }
}
