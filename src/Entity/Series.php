<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\SeriesRepository;

#[ORM\Entity(repositoryClass: SeriesRepository::class)]
#[UniqueEntity(fields: ['code', 'tenant'], message: 'This series is already in use for this tenant.')]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Series implements TenantAwareInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 4)]
    #[Gedmo\Versioned]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $source = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isActive = null;

    public function __toString(): string
    {
        return $this->getCode();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

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
}
