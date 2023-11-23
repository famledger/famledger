<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\VehicleTaxRepository;

#[ORM\Entity(repositoryClass: VehicleTaxRepository::class)]
#[Gedmo\Loggable]
class VehicleTax
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'taxes')]
    #[Gedmo\Versioned]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $paymentDate = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $nextDueDate = null;

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

    public function getPaymentDate(): ?DateTime
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(DateTime $paymentDate): static
    {
        $this->paymentDate = $paymentDate;

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

    public function getNextDueDate(): ?DateTime
    {
        return $this->nextDueDate;
    }

    public function setNextDueDate(DateTime $nextDueDate): static
    {
        $this->nextDueDate = $nextDueDate;

        return $this;
    }
}
