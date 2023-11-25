<?php

namespace App\Entity;

use App\Constant\InvoiceStatus;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Constant\DocumentType;
use App\Repository\TransactionRepository;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[Gedmo\Loggable]
class Transaction
{
    use LoggableTrait;

    const STATUS_AMOUNT_MISMATCH = 'amount-mismatch';
    const STATUS_PENDING         = 'pending';
    const STATUS_CONSOLIDATED    = 'consolidated';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Gedmo\Versioned]
    private ?Statement $statement = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Gedmo\Versioned]
    private ?DocumentType $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $bookingDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $valueDate = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Gedmo\Versioned]
    private ?int $sequenceNo = null;

    #[ORM\Column(length: 1024)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private bool $isConsolidated = false;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $status;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->status    = self::STATUS_PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;
        if ($type === DocumentType::ACCOUNT_STATEMENT) {
            $this->setIsConsolidated(true);
        }

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }


    public function getBookingDate(): ?DateTime
    {
        return $this->bookingDate;
    }

    public function setBookingDate(DateTime $bookingDate): static
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getValueDate(): ?DateTime
    {
        return $this->valueDate;
    }

    public function setValueDate(DateTime $valueDate): static
    {
        $this->valueDate = $valueDate;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if ($document->getInvoice() !== null) {
            $invoiceCustomer = $document->getInvoice()->getCustomer();

            if ($this->customer !== null and $invoiceCustomer !== $this->customer) {
                // TODO: re-enamble after cleanup
                //throw new \LogicException('Cannot add invoices from different customers to the same transaction.');
            }

            $this->customer = $invoiceCustomer;
        }

        $this->documents[] = $document;
        $document
            ->setTransaction($this)
            ->setSequenceNo($this->getSequenceNo());
        $this->updateConsolidationStatus();

        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            // Check if it's the last document with an invoice from the current customer
            $hasMoreInvoicesFromCustomer = $this->documents->exists(function ($key, Document $doc) {
                return null !== $doc->getInvoice() and $doc->getInvoice()->getCustomer() === $this->customer;
            });

            if (!$hasMoreInvoicesFromCustomer) {
                $this->customer = null; // If no more invoices from the customer, set customer to null
            }

            $document
                ->setTransaction(null)
                ->setSequenceNo(null);
        }

        $this->updateConsolidationStatus();

        return $this;
    }

    public function isIsConsolidated(): ?bool
    {
        return $this->isConsolidated;
    }

    public function setIsConsolidated(bool $isConsolidated): static
    {
        $this->isConsolidated = $isConsolidated;

        return $this;
    }

    public function updateConsolidationStatus(): void
    {
        $supportingDocuments = $this->documents->filter(function ($document) {
            // Exclude if it's an Attachment
            if ($document instanceof Attachment) {
                return false;
            }

            // Exclude if it's an Expense
            if ($this->amount < 0 and $document->getType() == DocumentType::EXPENSE) {
                return true;
            }

            // Exclude if it points to a non-valid Invoice
            if ($this->amount > 0 and $document->getType() == DocumentType::INCOME) {
                $invoice = $document->getInvoice();
                if (null !== $invoice and $invoice->getStatus() !== InvoiceStatus::VIGENTE) {
                    return false;
                }
            }

            // Include the document if none of the above conditions are met
            return true;
        });

        $hasDocuments = $supportingDocuments->count() > 0;

        /** @var Document $firstDocument */
        $firstDocument = $supportingDocuments->first();

        if (!$hasDocuments) {
            $this->setStatus(0 === count($this->documents)
                ? self::STATUS_PENDING
                : self::STATUS_AMOUNT_MISMATCH
            );
        } else {
            // income can be backed up by multiple documents
            if ($this->getAmount() > 0) {
                // Calculate the sum of all document amounts using array_reduce
                $documentAmountSum = array_reduce(
                    $supportingDocuments->toArray(),
                    fn($sum, $doc) => $sum + $doc->getAmount(),
                    0
                );
                $this->setStatus($documentAmountSum !== $this->getAmount()
                    ? self::STATUS_AMOUNT_MISMATCH
                    : self::STATUS_CONSOLIDATED
                );
            } else {
                // get first and only document
                if (null === $amount = $firstDocument->getAmount()) {
                    $firstDocument->setAmount($this->getAmount());
                    $this->setStatus(self::STATUS_CONSOLIDATED);
                } else {
                    $this->setStatus($amount === $this->getAmount()
                        ? self::STATUS_CONSOLIDATED
                        : self::STATUS_AMOUNT_MISMATCH
                    );
                }
            }
        }
        // Compare sum to transaction amount and set the isConsolidate flag accordingly
        $this->setIsConsolidated(self::STATUS_CONSOLIDATED === $this->getStatus());
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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

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
}
