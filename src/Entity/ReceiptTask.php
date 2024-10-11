<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\LiveModeDependent;
use App\Annotation\LiveModeFilterable;
use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\ReceiptTaskRepository;

#[ORM\Entity(repositoryClass: ReceiptTaskRepository::class)]
#[LiveModeFilterable(livemodeFieldName: 'live_mode')]
#[LiveModeDependent(livemodeFieldName: 'live_mode')]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class ReceiptTask implements LiveModeAwareInterface, TenantAwareInterface
{
    use LoggableTrait,
        TenantAwareTrait;

    const STATUS_PENDING   = 'pending';
    const STATUS_FAILED    = 'failed';
    const STATUS_COMPLETED = 'completed';

    public function __clone()
    {
        $this->id      = null;
        $this->receipt = null;
        $this->status  = self::STATUS_PENDING;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'receiptTask', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\ManyToOne]
    private ?Invoice $substitutesInvoice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $beneficiaryAccount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $originatorAccount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Customer $customer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Series $series = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $concept = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?DateTime $lastExecuted = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?bool $liveMode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $receiptTemplate = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requestData = null;

    #[ORM\OneToOne(mappedBy: 'task', cascade: ['persist', 'remove'])]
    private ?Receipt $receipt = null;

    public function __construct()
    {
        $this->setStatus(self::STATUS_PENDING);
    }

    public function __toString(): string
    {
        return $this->concept ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConcept(): ?string
    {
        return $this->concept;
    }

    public function setConcept(string $concept): static
    {
        $this->concept = $concept;

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

    public function getLastExecuted(): ?\DateTimeInterface
    {
        return $this->lastExecuted;
    }

    public function setLastExecuted(?\DateTimeInterface $lastExecuted): static
    {
        $this->lastExecuted = $lastExecuted;

        return $this;
    }

    public function isCompleted(): bool
    {
        return self::STATUS_COMPLETED === $this->status;
    }

    public function getLiveMode(): ?bool
    {
        return $this->liveMode;
    }

    public function setLiveMode(?bool $liveMode): static
    {
        $this->liveMode = $liveMode;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    public function setRequestData(?array $requestData): static
    {
        $this->requestData = $requestData;

        return $this;
    }

    public function getSubstitutesInvoice(): ?Invoice
    {
        return $this->substitutesInvoice;
    }

    public function setSubstitutesInvoice(?Invoice $substitutesInvoice): static
    {
        $this->substitutesInvoice = $substitutesInvoice;

        return $this;
    }

    public function getReceiptTemplate(): ?string
    {
        return $this->receiptTemplate;
    }

    public function setReceiptTemplate(?string $receiptTemplate): static
    {
        $this->receiptTemplate = $receiptTemplate;

        return $this;
    }

    public function isLiveMode(): ?bool
    {
        return $this->liveMode;
    }

    public function getBeneficiaryAccount(): ?Account
    {
        return $this->beneficiaryAccount;
    }

    public function setBeneficiaryAccount(?Account $beneficiaryAccount): static
    {
        $this->beneficiaryAccount = $beneficiaryAccount;

        return $this;
    }

    public function getOriginatorAccount(): ?Account
    {
        return $this->originatorAccount;
    }

    public function setOriginatorAccount(?Account $originatorAccount): static
    {
        $this->originatorAccount = $originatorAccount;

        return $this;
    }

    public function getReceipt(): ?Receipt
    {
        return $this->receipt;
    }

    public function setReceipt(?Receipt $receipt): static
    {
        // unset the owning side of the relation if necessary
        if ($receipt === null && $this->receipt !== null) {
            $this->receipt->setTask(null);
        }

        // set the owning side of the relation if necessary
        if ($receipt !== null && $receipt->getTask() !== $this) {
            $receipt->setTask($this);
        }

        $this->receipt = $receipt;

        return $this;
    }
}
