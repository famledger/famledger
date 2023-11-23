<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use App\Repository\TenantRepository;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[Gedmo\Loggable]
class Tenant
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 13)]
    #[Gedmo\Versioned]
    private ?string $rfc = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $apiKey = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $token = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $icon = null;

    public function __toString(): string
    {
        return $this->rfc . ' - ' . $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRfc(): ?string
    {
        return $this->rfc;
    }

    public function setRfc(string $rfc): static
    {
        $this->rfc = $rfc;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
