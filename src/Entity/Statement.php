<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\StatementRepository;

#[ORM\Entity(repositoryClass: StatementRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
#[ORM\UniqueConstraint(name: 'statement_year_month', columns: ['account_id', 'year', 'month'])]
class Statement implements TenantAwareInterface, FileOwnerInterface
{
    public function getOwnerKey(): ?string
    {
        return sprintf('%s-%s-%s',
            $this->getAccountNumber(),
            $this->getYear(),
            $this->getMonth(),
        );
    }

    use LoggableTrait;

    const STATUS_PENDING      = 'pending';
    const STATUS_CONSOLIDATED = 'consolidated';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'statement', targetEntity: Transaction::class, cascade: ['persist'])]
    private Collection $transactions;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $startingBalance = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $endingBalance = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $month = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $noDeposits = 0;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $noWithdrawals = 0;

    #[ORM\Column(length: 64)]
    #[Gedmo\Versioned]
    private ?string $accountNumber = null;

    #[ORM\ManyToOne(inversedBy: 'statements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Account $account = null;

    #[ORM\OneToOne(mappedBy: 'statement')]
    #[Gedmo\Versioned]
    private ?FinancialMonth $financialMonth = null;

    #[ORM\OneToOne]
    #[Gedmo\Versioned]
    private ?Document $document = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $status;

    #[ORM\Column(length: 16)]
    private ?string $type = null;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->status       = self::STATUS_PENDING;
    }

    public function __toString(): string
    {
        $account = $this->getAccount();

        return sprintf('Edo. Cta. %s %s-%02d',
            $account->getCaption(),
            $this->getYear(),
            $this->getMonth()
        );
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getStartingBalance(): ?int
    {
        return $this->startingBalance;
    }

    public function setStartingBalance(int $startingBalance): static
    {
        $this->startingBalance = $startingBalance;

        return $this;
    }

    public function getEndingBalance(): ?int
    {
        return $this->endingBalance;
    }

    public function setEndingBalance(int $endingBalance): static
    {
        $this->endingBalance = $endingBalance;

        return $this;
    }

    public function getBalanceDiff(): ?int
    {
        return ($this->endingBalance - $this->startingBalance);
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getNoDeposits(): ?int
    {
        return $this->noDeposits;
    }

    public function setNoDeposits(int $noDeposits): static
    {
        $this->noDeposits = $noDeposits;

        return $this;
    }

    public function getNoWithdrawals(): ?int
    {
        return $this->noWithdrawals;
    }

    public function setNoWithdrawals(int $noWithdrawals): static
    {
        $this->noWithdrawals = $noWithdrawals;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function getTransactionsOrdered(): Collection
    {
        $criteria = Criteria::create()
            ->orderBy(['sequenceNo' => Criteria::ASC]);

        return $this->getTransactions()->matching($criteria);
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setStatement($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getStatement() === $this) {
                $transaction->setStatement(null);
            }
        }

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        $this->setType($account->getType());

        return $this;
    }

    public function getFinancialMonth(): ?FinancialMonth
    {
        return $this->financialMonth;
    }

    public function setFinancialMonth(?FinancialMonth $financialMonth): static
    {
        // unset the owning side of the relation if necessary
        if ($financialMonth === null && $this->financialMonth !== null) {
            $this->financialMonth->setStatement(null);
        }

        // set the owning side of the relation if necessary
        if ($financialMonth !== null && $financialMonth->getStatement() !== $this) {
            $financialMonth->setStatement($this);
        }

        $this->financialMonth = $financialMonth;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isConsolidated(): bool
    {
        return self::STATUS_CONSOLIDATED === $this->status;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
