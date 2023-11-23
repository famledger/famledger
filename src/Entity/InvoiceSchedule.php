<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\InvoiceScheduleRepository;

#[ORM\Entity(repositoryClass: InvoiceScheduleRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class InvoiceSchedule implements TenantAwareInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invoiceSchedules')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Property $property = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $frequency = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?DateTime $scheduledDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?DateTime $nextIssueDate = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isActive = null;

    #[ORM\Column(length: 1024)]
    #[Gedmo\Versioned]
    private ?string $concept = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $taxCategory = null;

    #[ORM\OneToMany(mappedBy: 'invoiceSchedule', targetEntity: InvoiceTask::class)]
    private Collection $invoiceTasks;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $monthlyPaymentDay = null;

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

    #[ORM\Column(length: 32, options: ['default' => '99'])]
    private ?string $paymentForm = null;

    #[ORM\Column(length: 32, options: ['default' => 'PPD'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $invoiceUsage = null;

    public function __construct()
    {
        $this->invoiceTasks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->getProperty(), $this->getConcept());
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getScheduledDate(): ?DateTime
    {
        return $this->scheduledDate;
    }

    public function setScheduledDate(DateTime $scheduledDate): static
    {
        $this->scheduledDate = $scheduledDate;

        return $this;
    }

    public function getNextIssueDate(): ?DateTime
    {
        return $this->nextIssueDate;
    }

    public function setNextIssueDate(DateTime $nextIssueDate): static
    {
        $this->nextIssueDate = $nextIssueDate;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * @return Collection<int, InvoiceTask>
     */
    public function getInvoiceTasks(): Collection
    {
        return $this->invoiceTasks;
    }

    public function addInvoiceTask(InvoiceTask $invoiceTask): static
    {
        if (!$this->invoiceTasks->contains($invoiceTask)) {
            $this->invoiceTasks->add($invoiceTask);
            $invoiceTask->setInvoiceSchedule($this);
        }

        return $this;
    }

    public function removeInvoiceTask(InvoiceTask $invoiceTask): static
    {
        if ($this->invoiceTasks->removeElement($invoiceTask)) {
            // set the owning side to null (unless already changed)
            if ($invoiceTask->getInvoiceSchedule() === $this) {
                $invoiceTask->setInvoiceSchedule(null);
            }
        }

        return $this;
    }

    public function getMonthlyPaymentDay(): ?int
    {
        return $this->monthlyPaymentDay;
    }

    public function setMonthlyPaymentDay(?int $monthlyPaymentDay): static
    {
        $this->monthlyPaymentDay = $monthlyPaymentDay;

        return $this;
    }

    public function isMonthly(): bool
    {
        return $this->frequency === 'monthly';
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

    public function getInvoiceUsage(): ?string
    {
        return $this->invoiceUsage;
    }

    public function setInvoiceUsage(?string $invoiceUsage): static
    {
        $this->invoiceUsage = $invoiceUsage;

        return $this;
    }
}
