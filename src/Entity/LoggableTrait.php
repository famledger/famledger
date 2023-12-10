<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait LoggableTrait
{
    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    #[Gedmo\Timestampable(on: "create")]
    #[Gedmo\Versioned]
    private DateTime $created;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "created_by", referencedColumnName: "id")]
    #[Gedmo\Blameable(on: "create")]
    #[Gedmo\Versioned]
    private ?User $createdBy;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    #[Gedmo\Timestampable(on: "update")]
    #[Gedmo\Versioned]
    private DateTime $updated;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "updated_by", referencedColumnName: "id")]
    #[Gedmo\Blameable(on: "update")]
    #[Gedmo\Versioned]
    private ?User $updatedBy;

    public function setCreated(DateTime $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated(): ?DateTime
    {
        return $this->created;
    }

    public function setUpdated(DateTime $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    public function getUpdated(): ?DateTime
    {
        return $this->updated;
    }

    public function setCreatedBy(User $createdBy = null): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setUpdatedBy(?User $updatedBy = null): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }
}