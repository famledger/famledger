<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\PropertyRepository;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Property implements TenantAwareInterface, FileOwnerInterface
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

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $caption = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $address = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $cadastralNumber = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isActive = null;

    #[ORM\OneToMany(mappedBy: 'property', targetEntity: InvoiceSchedule::class)]
    private Collection $invoiceSchedules;

    #[ORM\Column(length: 16, unique: true)]
    #[Gedmo\Versioned]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $matchString = null;

    #[ORM\OneToMany(mappedBy: 'property', targetEntity: Invoice::class)]
    private Collection $invoices;

    public function getOwnerKey(): ?string
    {
        return $this->slug;
    }

    public function __construct()
    {
        $this->invoiceSchedules = new ArrayCollection();
        $this->invoices         = new ArrayCollection();
        $this->matchString      = '';
    }

    public function __toString(): string
    {
        return $this->slug;
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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCadastralNumber(): ?string
    {
        return $this->cadastralNumber;
    }

    public function setCadastralNumber(?string $cadastralNumber): static
    {
        $this->cadastralNumber = $cadastralNumber;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, InvoiceSchedule>
     */
    public function getInvoiceSchedules(): Collection
    {
        return $this->invoiceSchedules;
    }

    public function addInvoiceSchedule(InvoiceSchedule $invoiceSchedule): static
    {
        if (!$this->invoiceSchedules->contains($invoiceSchedule)) {
            $this->invoiceSchedules->add($invoiceSchedule);
            $invoiceSchedule->setProperty($this);
        }

        return $this;
    }

    public function removeInvoiceSchedule(InvoiceSchedule $invoiceSchedule): static
    {
        if ($this->invoiceSchedules->removeElement($invoiceSchedule)) {
            // set the owning side to null (unless already changed)
            if ($invoiceSchedule->getProperty() === $this) {
                $invoiceSchedule->setProperty(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getMatchString(): ?string
    {
        return $this->matchString;
    }

    public function setMatchString(string $matchString): static
    {
        $this->matchString = $matchString;

        return $this;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setProperty($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getProperty() === $this) {
                $invoice->setProperty(null);
            }
        }

        return $this;
    }
}
