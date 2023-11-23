<?php

namespace App\Entity;

use App\Annotation\TenantFilterable;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use IntlDateFormatter;

use App\Annotation\TenantDependent;
use App\Repository\FinancialMonthRepository;

#[ORM\Entity(repositoryClass: FinancialMonthRepository::class)]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class FinancialMonth implements TenantAwareInterface
{
    use LoggableTrait;

    const STATUS_PENDING  = 'pending';
    const STATUS_COMPLETE = 'complete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Gedmo\Versioned]
    private ?int $month = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $status = null;

    #[ORM\OneToMany(mappedBy: 'financialMonth', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\OneToOne(inversedBy: 'financialMonth')]
    #[Gedmo\Versioned]
    private ?Statement $statement = null;

    #[ORM\ManyToOne(inversedBy: 'financialMonths')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Account $account = null;


    public function __construct()
    {
        $this->status    = self::STATUS_PENDING;
        $this->documents = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getPath() ?? '';
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

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function setYear(string $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getPath(): ?string
    {
        $formatter = new IntlDateFormatter(
            'es_ES',
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'UTC',
            IntlDateFormatter::GREGORIAN,
            'MMMM'
        );

        $date      = new DateTime(sprintf('%d-%02d-01', $this->year, $this->month));
        $monthName = ucfirst($formatter->format($date));

        return sprintf('%s/%d/%d-%02d %s',
            $this->account->getNumber(),
            $this->year,
            $this->year,
            $this->month,
            $monthName
        );
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setFinancialMonth($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getFinancialMonth() === $this) {
                $document->setFinancialMonth(null);
            }
        }

        return $this;
    }

    public function getDocumentBySequenceNo(int $sequenceNo): ?Document
    {
        $expr     = Criteria::expr();
        $criteria = Criteria::create()->where($expr->eq('sequenceNo', $sequenceNo));

        $documents = $this->getDocuments();
        $matches   = $documents->matching($criteria);

        return $matches->count() > 0 ? $matches->first() : null;
    }

    public function getStatement(): ?Statement
    {
        return $this->statement;
    }

    public function setStatement(?Statement $statement): static
    {
        $this->statement = $statement;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }
}
