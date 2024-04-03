<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\LiveModeDependent;
use App\Annotation\LiveModeFilterable;
use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\InvoiceTaskRepository;

#[ORM\Entity(repositoryClass: InvoiceTaskRepository::class)]
#[LiveModeFilterable(livemodeFieldName: 'live_mode')]
#[LiveModeDependent(livemodeFieldName: 'live_mode')]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class InvoiceTask implements LiveModeAwareInterface, TenantAwareInterface
{
    use LoggableTrait;

    const STATUS_PENDING   = 'pending';
    const STATUS_FAILED    = 'failed';
    const STATUS_COMPLETED = 'completed';

    public function __clone()
    {
        $this->id      = null;
        $this->invoice = null;
        $this->status  = self::STATUS_PENDING;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invoiceTasks')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?InvoiceSchedule $invoiceSchedule = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Property $property = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $firstDate = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $concept = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?DateTime $lastExecuted = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $month = null;

    #[ORM\OneToOne(inversedBy: 'invoiceTask')]
    #[Gedmo\Versioned]
    private ?Invoice $invoice = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?bool $liveMode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $invoiceTemplate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Customer $customer = null;

    #[ORM\ManyToOne]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Series $series = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $taxCategory = null;

    #[ORM\Column(length: 32)]
    private ?string $paymentForm = null;

    #[ORM\Column(length: 32)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requestData = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $invoiceUsage = null;

    #[ORM\ManyToOne]
    private ?Invoice $substitutesInvoice = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $regimeType = null;

    public function __toString(): string
    {
        return $this->concept ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceSchedule(): ?InvoiceSchedule
    {
        return $this->invoiceSchedule;
    }

    public function setInvoiceSchedule(?InvoiceSchedule $invoiceSchedule): static
    {
        $this->invoiceSchedule = $invoiceSchedule;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getFirstDate(): ?DateTime
    {
        return $this->firstDate;
    }

    public function setFirstDate(DateTime $firstDate): static
    {
        $this->firstDate = $firstDate;

        return $this;
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

    public function getLastExecuted(): ?DateTime
    {
        return $this->lastExecuted;
    }

    public function setLastExecuted(?DateTime $lastExecuted): static
    {
        $this->lastExecuted = $lastExecuted;

        return $this;
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

    public function isCompleted(): bool
    {
        return self::STATUS_COMPLETED === $this->status;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
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

    public function getInvoiceTemplate(): ?string
    {
        return $this->invoiceTemplate;
    }

    public function setInvoiceTemplate(?string $invoiceTemplate): static
    {
        $this->invoiceTemplate = $invoiceTemplate;

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

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

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

    public function getTaxCategory(): ?string
    {
        return $this->taxCategory;
    }

    public function setTaxCategory(string $taxCategory): static
    {
        $this->taxCategory = $taxCategory;

        return $this;
    }

    public function getPaymentForm(): ?string
    {
        return $this->paymentForm;
    }

    public function setPaymentForm(string $paymentForm): static
    {
        $this->paymentForm = $paymentForm;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

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

    public function getInvoiceUsage(): ?string
    {
        return $this->invoiceUsage;
    }

    public function setInvoiceUsage(?string $invoiceUsage): static
    {
        $this->invoiceUsage = $invoiceUsage;

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

    public function getRegimeType(): ?string
    {
        return $this->regimeType;
    }

    public function setRegimeType(?string $regimeType): static
    {
        $this->regimeType = $regimeType;

        return $this;
    }
}
