<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\EDocRepository;

#[ORM\Entity(repositoryClass: EDocRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
#[ORM\UniqueConstraint(name: 'edoc_checksum', columns: ['checksum'])]
class EDoc implements TenantAwareInterface, FileOwnerInterface
{
    use FileOwnerTrait,
        LoggableTrait,
        TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $type = null;

    #[ORM\Column(length: 4)]
    #[Gedmo\Versioned]
    private ?string $format = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $filename = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Versioned]
    private ?DateTime $issueDate = null;

    public function __toString(): string
    {
        return $this->getId();
    }

    public function getSelf(): static
    {
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): static
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getIssueDate(): ?DateTime
    {
        return $this->issueDate;
    }

    public function setIssueDate(?DateTime $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }
}
