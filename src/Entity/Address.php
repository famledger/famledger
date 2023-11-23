<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use App\Repository\AddressRepository;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[UniqueEntity(fields: ['identifier', 'customer'])]
#[ORM\UniqueConstraint(fields: ['identifier', 'customer'])]
#[Gedmo\Loggable]
class Address
{
    use LoggableTrait;

    public function __toString(): string
    {
        return $this->identifier;   // TODO: Implement __toString() method.
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $calle = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $noExterior = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?string $noInterior = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $colonia = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $localidad = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $municipio = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $estado = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $pais = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $cp = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Customer $customer = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $identifier = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $checksum = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalle(): ?string
    {
        return $this->calle;
    }

    public function setCalle(string $calle): static
    {
        $this->calle = $calle;

        return $this;
    }

    public function getNoExterior(): ?string
    {
        return $this->noExterior;
    }

    public function setNoExterior(string $noExterior): static
    {
        $this->noExterior = $noExterior;

        return $this;
    }

    public function getNoInterior(): ?string
    {
        return $this->noInterior;
    }

    public function setNoInterior(string $noInterior): static
    {
        $this->noInterior = $noInterior;

        return $this;
    }

    public function getColonia(): ?string
    {
        return $this->colonia;
    }

    public function setColonia(string $colonia): static
    {
        $this->colonia = $colonia;

        return $this;
    }

    public function getLocalidad(): ?string
    {
        return $this->localidad;
    }

    public function setLocalidad(string $localidad): static
    {
        $this->localidad = $localidad;

        return $this;
    }

    public function getMunicipio(): ?string
    {
        return $this->municipio;
    }

    public function setMunicipio(string $municipio): static
    {
        $this->municipio = $municipio;

        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function getPais(): ?string
    {
        return $this->pais;
    }

    public function setPais(string $pais): static
    {
        $this->pais = $pais;

        return $this;
    }

    public function getCp(): ?string
    {
        return $this->cp;
    }

    public function setCp(string $cp): static
    {
        $this->cp = $cp;

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

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): static
    {
        $this->identifier = $identifier;

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
}
