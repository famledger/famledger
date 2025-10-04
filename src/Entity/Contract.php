<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\ContractRepository;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Contract implements TenantAwareInterface, FileOwnerInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Property::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Property $property = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $signingDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Gedmo\Versioned]
    private ?string $monthlyAmount = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notaryName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isActive = true;

    public function getOwnerKey(): ?string
    {
        return sprintf('%s-%s', $this->customer?->getRfc(), $this->property?->getSlug());
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s to %s)', 
            $this->customer?->getName() ?? 'N/A',
            $this->property?->getCaption() ?? 'N/A',
            $this->startDate?->format('M Y') ?? 'N/A',
            $this->endDate?->format('M Y') ?? 'N/A'
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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;
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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getSigningDate(): ?\DateTimeInterface
    {
        return $this->signingDate;
    }

    public function setSigningDate(?\DateTimeInterface $signingDate): static
    {
        $this->signingDate = $signingDate;
        return $this;
    }

    public function getMonthlyAmount(): ?string
    {
        return $this->monthlyAmount;
    }

    public function setMonthlyAmount(string $monthlyAmount): static
    {
        $this->monthlyAmount = $monthlyAmount;
        return $this;
    }

    public function getNotaryName(): ?string
    {
        return $this->notaryName;
    }

    public function setNotaryName(?string $notaryName): static
    {
        $this->notaryName = $notaryName;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getDaysUntilExpiration(): string
    {
        if (!$this->endDate) {
            return 0;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($this->endDate);
        
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->getDaysUntilExpiration() <= $days && $this->getDaysUntilExpiration() >= 0;
    }


}