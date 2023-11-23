<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\VehicleRepository;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[Gedmo\Loggable]
class Vehicle implements FileOwnerInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Gedmo\Versioned]
    private ?string $UID = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $owner = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $licensePlate = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $make = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $model = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $color = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $vin = null;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: VehicleTax::class)]
    private Collection $taxes;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: VehicleInsurance::class)]
    private Collection $insurances;

    public function __toString(): string
    {
        return $this->getUID();
    }

    public function getOwnerKey(): ?string
    {
        return $this->getUID();
    }

    public function __construct()
    {
        $this->taxes      = new ArrayCollection();
        $this->insurances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUID(): ?string
    {
        return $this->UID;
    }

    public function setUID(string $UID): static
    {
        $this->UID = $UID;

        return $this;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): static
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(string $make): static
    {
        $this->make = $make;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getVin(): ?string
    {
        return $this->vin;
    }

    public function setVin(?string $vin): static
    {
        $this->vin = $vin;

        return $this;
    }

    /**
     * @return Collection<int, VehicleTax>
     */
    public function getTaxes(): Collection
    {
        return $this->taxes;
    }

    public function addVehicleTax(VehicleTax $vehicleTax): static
    {
        if (!$this->taxes->contains($vehicleTax)) {
            $this->taxes->add($vehicleTax);
            $vehicleTax->setVehicle($this);
        }

        return $this;
    }

    public function removeVehicleTax(VehicleTax $vehicleTax): static
    {
        if ($this->taxes->removeElement($vehicleTax)) {
            // set the owning side to null (unless already changed)
            if ($vehicleTax->getVehicle() === $this) {
                $vehicleTax->setVehicle(null);
            }
        }

        return $this;
    }

    public function getLatestTax(): ?VehicleTax
    {
        $criteria = Criteria::create()
            ->orderBy(['paymentDate' => Criteria::DESC])
            ->setMaxResults(1);

        $latestTax = $this->taxes->matching($criteria)->first();

        return $latestTax ?: null;
    }

    /**
     * @return Collection<int, VehicleInsurance>
     */
    public function getInsurances(): Collection
    {
        return $this->insurances;
    }

    public function addVehicleInsurance(VehicleInsurance $vehicleInsurance): static
    {
        if (!$this->insurances->contains($vehicleInsurance)) {
            $this->insurances->add($vehicleInsurance);
            $vehicleInsurance->setVehicle($this);
        }

        return $this;
    }

    public function removeVehicleInsurance(VehicleInsurance $vehicleInsurance): static
    {
        if ($this->insurances->removeElement($vehicleInsurance)) {
            // set the owning side to null (unless already changed)
            if ($vehicleInsurance->getVehicle() === $this) {
                $vehicleInsurance->setVehicle(null);
            }
        }

        return $this;
    }

    public function getLatestInsurance(): ?VehicleInsurance
    {
        $criteria = Criteria::create()
            ->orderBy(['paymentDate' => Criteria::DESC])
            ->setMaxResults(1);

        $latestInsurance = $this->insurances->matching($criteria)->first();

        return $latestInsurance ?: null;
    }
}
