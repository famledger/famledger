<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Constant\DocumentType;
use App\Constant\InvoiceStatus;
use App\Repository\DocumentRepository;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string', length: 20)]
#[ORM\DiscriminatorMap([
    'document'    => Document::class,
    'tax-payment' => TaxPayment::class,
    'attachment'  => Attachment::class,
    'tax-notice'  => TaxNotice::class,
])]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
//#[UniqueEntity(fields: ['checksum'], message: 'A document with this checksum already exists')]
#[Gedmo\Loggable]
class Document implements TenantAwareInterface
{
    use LoggableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['access' => 'PROPERTY'])]
    protected ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 32)]
    #[Gedmo\Versioned]
    private ?DocumentType $type = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $subType = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    #[Gedmo\Versioned]
    private ?FinancialMonth $financialMonth = null;

    #[ORM\OneToOne]
    #[Gedmo\Versioned]
    private ?Statement $statement = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Gedmo\Versioned]
    private ?int $sequenceNo = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $filename = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private bool $isConsolidated = false;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[Gedmo\Versioned]
    private ?Transaction $transaction = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\OneToOne(mappedBy: 'document')]
    #[Gedmo\Versioned]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $checksum = null;

    #[ORM\OneToOne(mappedBy: 'parent')]
    private ?Attachment $attachment = null;

    #[ORM\Column(nullable: true)]
    private ?array $specs = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $issueDate = null;

    #[ORM\ManyToOne]
    private ?Property $property = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $month = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isRelated = null;

    public function __toString(): string
    {
        return $this->filename;
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

    public function getType(): ?DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeString(): ?string
    {
        return $this->type?->value;
    }

    public function setTypeString(string $type): static
    {
        $this->type = DocumentType::from($type);

        return $this;
    }

    public function getFinancialMonth(): ?FinancialMonth
    {
        return $this->financialMonth;
    }

    public function setFinancialMonth(?FinancialMonth $financialMonth): static
    {
        $this->financialMonth = $financialMonth;

        return $this;
    }

    public function getSequenceNo(): ?int
    {
        return $this->sequenceNo;
    }

    public function setSequenceNo(?int $sequenceNo): static
    {
        $this->sequenceNo = $sequenceNo;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function affectsTransactionSum(): bool
    {
        // documents that affect the transaction they are associated with are:
        // - expenses
        // - invoices that have not been canceled
        $isAnnotation   = $this->getType() === DocumentType::ANNOTATION;
        $isExpense      = $this->getType() === DocumentType::EXPENSE;
        $isTax          = $this->getType() === DocumentType::TAX;
        $isValidInvoice = (
            $this->getType() === DocumentType::INCOME
            and null !== $this->getInvoice()
                and $this->getInvoice()->getStatus() === InvoiceStatus::VIGENTE
        );

        return $isExpense or $isTax or $isAnnotation or $isValidInvoice;
    }

    public function getIsConsolidated(): bool
    {
        return $this->isConsolidated;
    }

    public function setIsConsolidated(bool $isConsolidated): static
    {
        $this->isConsolidated = $isConsolidated;

        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        // unset the owning side of the relation if necessary
        if ($invoice === null && $this->invoice !== null) {
            if ($this instanceof Attachment) {
                $this->invoice->setAttachment(null);
            } else {
                $this->invoice->setDocument(null);
            }
        }

        // set the owning side of the relation if necessary
        if ($invoice !== null && $invoice->getDocument() !== $this) {
            if ($this instanceof Attachment) {
                $invoice->setAttachment($this);
            } else {
                $invoice->setDocument($this);
            }
        }

        $this->invoice = $invoice;

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

    public function isAnnex(): bool
    {
        return (DocumentType::ANNEX === $this->getType());
    }

    public function isAnnotation(): bool
    {
        return (DocumentType::ANNOTATION === $this->getType());
    }

    public function isAttachment(): bool
    {
        return ($this instanceof Attachment);
    }

    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function setAttachment(?Attachment $attachment): static
    {
        // unset the owning side of the relation if necessary
        if ($attachment === null && $this->attachment !== null) {
            $this->attachment->setParent(null);
        }

        // set the owning side of the relation if necessary
        if ($attachment !== null && $attachment->getParent() !== $this) {
            $attachment->setParent($this);
        }

        $this->attachment = $attachment;

        return $this;
    }

    public function getSpecs(): ?array
    {
        return $this->specs;
    }

    public function setSpecs(?array $specs): static
    {
        $this->specs = $specs;
        if (null !== $specs) {
            $this->setYear($specs['year'] ?? null);
            $this->setMonth($specs['month'] ?? null);
            $this->setSubType($specs['subType'] ?? null);
        }

        return $this;
    }

    public function getIssueDate(): ?DateTime
    {
        return $this->issueDate;
    }

    public function setIssueDate(?DateTime $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(?int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getSubType(): ?string
    {
        return $this->subType;
    }

    public function setSubType(?string $subType): static
    {
        $this->subType = $subType;

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

    public function getIsRelated(): ?bool
    {
        return (bool)$this->isRelated;
    }

    public function setIsRelated(?bool $isRelated): static
    {
        $this->isRelated = (bool)$isRelated;

        return $this;
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
}
