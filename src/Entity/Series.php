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
    use LoggableTrait,
        TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 4)]
    #[Gedmo\Versioned]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $source = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isActive = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $type = null;

    public function __toString(): string
    {
        return $this->getCode();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
