<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\VehicleInsuranceRepository;

#[ORM\Entity(repositoryClass: VehicleInsuranceRepository::class)]
#[Gedmo\Loggable]
class VehicleInsurance
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'insurances')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $provider = null;

    #[ORM\Column(length: 64)]
    #[Gedmo\Versioned]
    private ?string $policyNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $expiryDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $agent = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    public function __toString(): string
    {
        return $this->getPolicyNumber() ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getPolicyNumber(): ?string
    {
        return $this->policyNumber;
    }

    public function setPolicyNumber(string $policyNumber): static
    {
        $this->policyNumber = $policyNumber;

        return $this;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getExpiryDate(): ?DateTime
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(DateTime $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(?string $agent): static
    {
        $this->agent = $agent;

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
}
