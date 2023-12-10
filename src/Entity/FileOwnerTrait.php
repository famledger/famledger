<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait FileOwnerTrait
{
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $ownerId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ownerType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ownerKey = null;

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setOwnerId(?int $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function setOwnerType(?string $ownerType): static
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerKey(): ?string
    {
        return $this->ownerKey;
    }

    public function setOwnerKey(?string $ownerKey): static
    {
        $this->ownerKey = $ownerKey;

        return $this;
    }
}