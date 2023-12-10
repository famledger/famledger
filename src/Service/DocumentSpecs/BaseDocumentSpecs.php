<?php

namespace App\Service\DocumentSpecs;

use DateTime;
use Exception;

use App\Constant\DocumentType;

abstract class BaseDocumentSpecs
{
    protected ?int    $month             = null;
    protected ?int    $year              = null;
    protected ?string $accountNumber     = null;
    private ?string   $suggestedFilename = null;
    private string    $type;
    private ?string   $subType           = null;
    private ?int      $amount            = null;
    private ?DateTime $issueDate         = null;
    private ?string   $description       = null;

    abstract public function getDocumentType(): DocumentType;

    /**
     * @throws Exception
     */
    public function __construct(?array $params = null)
    {
        $this->type = basename(str_replace('\\', '/', get_class($this)));
        if ($params) {
            foreach ($params as $key => $value) {
                if ($key === 'issueDate' && $value !== null and !($value instanceof DateTime)) {
                    $value = DateTime::createFromFormat('Y-m-d', $value);
                }
                $this->$key = $value;
            }
        }
    }

    public function setSuggestedFilename(string $suggestedFilename): self
    {
        $this->suggestedFilename = $suggestedFilename;

        return $this;
    }

    public function getSuggestedFilename(): ?string
    {
        return $this->suggestedFilename;
    }

    /**
     * @return string|null
     */
    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): self
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }


    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setMonth(?int $month): self
    {
        $this->month = $month;

        return $this;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubType(): ?string
    {
        return $this->subType;
    }

    public function setSubType(?string $subType): self
    {
        $this->subType = $subType;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function serialize(): array
    {
        return [
            'type'            => $this->type,
            'month'           => $this->month,
            'year'            => $this->year,
            'accountNumber'   => $this->accountNumber,
            'amount'          => $this->amount,
            'suggestFilename' => $this->suggestedFilename,
            'issueDate'       => $this->issueDate ? $this->issueDate->format('Y-m-d') : ''
        ];
    }
}
