<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Repository\CustomerRepository;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Customer implements TenantAwareInterface, FileOwnerInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 13)]
    #[Gedmo\Versioned]
    private ?string $rfc = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Address::class, cascade: ['persist'])]
    private Collection $addresses;

    #[ORM\OneToOne]
    #[Gedmo\Versioned]
    private ?Address $defaultAddress = null;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Transaction::class)]
    private Collection $transactions;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Email::class, cascade: ['persist'])]
    private Collection $emails;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $color = null;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Account::class, cascade: ['persist'])]
    private Collection $accounts;

    public function getOwnerKey(): ?string
    {
        return $this->rfc;
    }

    public function __construct()
    {
        $this->invoices     = new ArrayCollection();
        $this->addresses    = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->emails       = new ArrayCollection();
        $this->accounts     = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->rfc . ' - ' . $this->name;
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
            $invoice->setCustomer($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getCustomer() === $this) {
                $invoice->setCustomer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): static
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setCustomer($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): static
    {
        if ($this->addresses->removeElement($address)) {
            // set the owning side to null (unless already changed)
            if ($address->getCustomer() === $this) {
                $address->setCustomer(null);
            }
        }

        return $this;
    }

    public function getDefaultAddress(): ?Address
    {
        return $this->defaultAddress;
    }

    public function setDefaultAddress(?Address $defaultAddress): static
    {
        $this->defaultAddress = $defaultAddress;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setCustomer($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getCustomer() === $this) {
                $transaction->setCustomer(null);
            }
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<int, Email>
     */
    public function getEmails(): Collection
    {
        return $this->emails;
    }

    public function addEmail(Email $email): static
    {
        if (!$this->emails->contains($email)) {
            $this->emails->add($email);
            $email->setCustomer($this);
        }

        return $this;
    }

    public function removeEmail(Email $email): static
    {
        if ($this->emails->removeElement($email)) {
            // set the owning side to null (unless already changed)
            if ($email->getCustomer() === $this) {
                $email->setCustomer(null);
            }
        }

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getDefaultAccount(): ?Account
    {
        foreach ($this->getAccounts() as $account) {
            // for now, return the first account
            return $account;
//            if ($account->getIsDefault()) {
//                return $account;
//            }
        }

        return null;
    }

    /**
     * @return Collection<int, Account>
     */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(Account $account): static
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
            $account->setCustomer($this);
        }

        return $this;
    }

    public function removeAccount(Account $account): static
    {
        if ($this->accounts->removeElement($account)) {
            // set the owning side to null (unless already changed)
            if ($account->getCustomer() === $this) {
                $account->setCustomer(null);
            }
        }

        return $this;
    }
}
