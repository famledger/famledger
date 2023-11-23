<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\AccountRepository;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Account implements TenantAwareInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 64)]
    #[Gedmo\Versioned]
    private ?string $number = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $caption = null;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: Statement::class)]
    private Collection $statements;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: FinancialMonth::class)]
    private Collection $financialMonths;

    #[ORM\Column(length: 32, unique: true, nullable: false)]
    #[Gedmo\Versioned]
    private ?string $clabe = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $username = null;

    public function __construct()
    {
        $this->statements = new ArrayCollection();
        $this->financialMonths = new ArrayCollection();
    }

    public function __toString(): string
    {
     return $this->getCaption() . ' [' . $this->getNumber() . ']';
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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    /**
     * @return Collection<int, Statement>
     */
    public function getStatements(): Collection
    {
        return $this->statements;
    }

    public function addStatement(Statement $statement): static
    {
        if (!$this->statements->contains($statement)) {
            $this->statements->add($statement);
            $statement->setAccount($this);
        }

        return $this;
    }

    public function removeStatement(Statement $statement): static
    {
        if ($this->statements->removeElement($statement)) {
            // set the owning side to null (unless already changed)
            if ($statement->getAccount() === $this) {
                $statement->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FinancialMonth>
     */
    public function getFinancialMonths(): Collection
    {
        return $this->financialMonths;
    }

    public function addFinancialMonth(FinancialMonth $financialMonth): static
    {
        if (!$this->financialMonths->contains($financialMonth)) {
            $this->financialMonths->add($financialMonth);
            $financialMonth->setAccount($this);
        }

        return $this;
    }

    public function removeFinancialMonth(FinancialMonth $financialMonth): static
    {
        if ($this->financialMonths->removeElement($financialMonth)) {
            // set the owning side to null (unless already changed)
            if ($financialMonth->getAccount() === $this) {
                $financialMonth->setAccount(null);
            }
        }

        return $this;
    }

    public function getClabe(): ?string
    {
        return $this->clabe;
    }

    public function setClabe(string $clabe): static
    {
        $this->clabe = $clabe;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }
}
