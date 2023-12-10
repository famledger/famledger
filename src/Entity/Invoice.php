<?php

namespace App\Entity;

use App\Constant\SeriesType;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Annotation\LiveModeDependent;
use App\Annotation\LiveModeFilterable;
use App\Annotation\TenantDependent;
use App\Annotation\TenantFilterable;
use App\Constant\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Service\MonthConverter;
use App\Service\Strategies\StrategyHelper;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[LiveModeFilterable(livemodeFieldName: 'live_mode')]
#[LiveModeDependent (livemodeFieldName: 'live_mode')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string', length: 20)]
#[ORM\DiscriminatorMap([
    SeriesType::INVOICE => Invoice::class,
    SeriesType::PAYMENT => Receipt::class,
])]
#[TenantDependent(tenantFieldName: 'tenant_id')]
#[TenantFilterable(tenantFieldName: 'tenant_id')]
#[Gedmo\Loggable]
class Invoice implements TenantAwareInterface, LiveModeAwareInterface
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

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    #[Gedmo\Versioned]
    private ?Customer $customer = null;

    #[ORM\OneToOne(inversedBy: 'invoice')]
    #[Gedmo\Versioned]
    private ?Document $document = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $series = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $number = null;

    #[ORM\Column(length: 13)]
    #[Gedmo\Versioned]
    private ?string $recipientRFC = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $recipientName = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $currency = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $amount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Versioned]
    private ?DateTime $issueDate = null;

    #[ORM\Column(length: 16)]
    #[Gedmo\Versioned]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?array $data = null;

    #[ORM\Column(length: 1024, unique: true, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $urlPdf = null;

    #[ORM\Column(length: 1024, unique: true, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $urlXml = null;

    #[ORM\Column(length: 1024, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Gedmo\Versioned]
    private ?int $month = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private bool $isComplete = false;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    #[Gedmo\Versioned]
    private ?Property $property = null;

    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?bool $liveMode = null;

    #[ORM\OneToOne(mappedBy: 'invoice')]
    #[Gedmo\Versioned]
    private ?InvoiceTask $invoiceTask = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $checksumPdf = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $checksumXml = null;

    #[ORM\OneToOne(inversedBy: 'substitutedByInvoice', targetEntity: self::class)]
    private ?self $substitutesInvoice = null;

    #[ORM\OneToOne(mappedBy: 'substitutesInvoice', targetEntity: self::class)]
    private ?self $substitutedByInvoice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $cancellationDate = null;

    #[ORM\Column(nullable: true)]
    private ?array $cancellationData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $paymentDate = null;

    #[ORM\OneToOne(inversedBy: 'invoice')]
    private ?Attachment $attachment = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    private ?Receipt $payment = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    private ?ReceiptTask $receiptTask = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cfdi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return sprintf('%s-%s', $this->series, $this->number);
    }

    public function getInvoiceUid(): static
    {
        return $this;
    }

    public function getInvoicePeriod(): string
    {
        return sprintf('%s %s', $this->getYear(), MonthConverter::fromNumericMonth($this->getMonth()));
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

    public function getHID(): ?string
    {
        return $this->series . '-' . $this->number;
    }

    public function getFolioFiscalUUID(): ?string
    {
        return $this->getData('folioFiscalUUID');
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(string $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getRecipientRFC(): ?string
    {
        return $this->recipientRFC;
    }

    public function setRecipientRFC(string $recipientRFC): static
    {
        $this->recipientRFC = $recipientRFC;

        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = 'XXX' === $currency ? null : $currency;

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

    public function getIssueDate(): ?DateTime
    {
        return $this->issueDate;
    }

    public function setIssueDate(DateTime $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getDisplayStatus(): ?string
    {
        return $this->getCancellationStatus() ?? $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getData(?string $key = null): mixed
    {
        return null === $key
            ? $this->data
            : ($this->data[$key] ?? null);
    }

    public function setData(?array $data): static
    {
        $this->data   = $data;
        $this->urlPdf = $data['descargaArchivoPDF'] ?? null;
        $this->urlXml = $data['descargaXmlCFDi'] ?? null;
        if (isset($data['peticion'])) {
            $peticion = json_decode($data['peticion'], true);
            if (isset($peticion['CFDi']['Partidas'])) {
                $this->description = $peticion['CFDi']['Partidas'][0]['descripcion'] ?? null;

                $pattern1 = '/Periodo:\s+\d{1,2}\s+de\s+(\w+)\s+(?:de )(\d{4})\s+al\s+(\d{1,2}\s+de\s+\w+\s+(?:de )\d{4})/i';
                $pattern2 = '/Periodo:\s+\d{1,2}\s+de\s+(\w+)\s+al\s+\d{1,2}\s+de\s+(?:\w+)\s+(\d{4})/i';
                if (preg_match($pattern1, $this->description, $matches)) {
                    $month = $matches[1];
                    $year  = $matches[2];
                    if (!is_numeric($month)) {
                        $month = StrategyHelper::convertMonthToInt($month);
                    }
                    $this->setMonth((int)$month);
                    $this->setYear((int)$year);
                } elseif (preg_match($pattern2, $this->description, $matches)) {
                    $month = $matches[1];
                    $year  = $matches[2];
                    if (!is_numeric($month)) {
                        $month = StrategyHelper::convertMonthToInt($month);
                    }
                    $this->setMonth((int)$month);
                    $this->setYear((int)$year);
                } elseif (preg_match('/(enero|febrero|marzo) 2018$/', $this->description, $matches)) {
                    $month = StrategyHelper::convertMonthToInt($matches[1]);
                    $this->setMonth($month);
                    $this->setYear(2018);
                }
                if ($this->getSeries() === 'A'
                    and in_array($this->getNumber(), [527, 528])
                        and $this->getTenant()->getId() === 1
                ) {
                    $this->setYear(2018);
                    $this->setMonth(8);
                }
            }
        }
        // setStatus will throw an exception if the status is invalid
        $this->setStatus(match (strtolower($data['estadoCFDi'] ?? '')) {
            InvoiceStatus::VIGENTE   => InvoiceStatus::VIGENTE,
            InvoiceStatus::CANCELADO => InvoiceStatus::CANCELADO,
            default                  => 'invalid'
        });

        if (isset($data['infoCancelacion'])) {
            $this->setCancellationData($data['infoCancelacion']);
        }

        return $this;
    }

    public function getCancellationStatus(): ?string
    {
        if (null !== $cancellationData = $this->getData('infoCancelacion')) {
            $lastEntry = end($cancellationData);

            return $lastEntry['estatus'] ?? null;
        }

        return null;
    }

    public function getUrlPdf(): ?string
    {
        return $this->urlPdf;
    }

    public function setUrlPdf(?string $urlPdf): static
    {
        $this->urlPdf = $urlPdf;

        return $this;
    }

    public function getUrlXml(): ?string
    {
        return $this->urlXml;
    }

    public function setUrlXml(?string $urlXml): static
    {
        $this->urlXml = $urlXml;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getLiveMode(): ?bool
    {
        return $this->liveMode;
    }

    public function setLiveMode(?bool $liveMode): static
    {
        $this->liveMode = $liveMode;

        return $this;
    }

    public function getIsComplete(): ?bool
    {
        return $this->isComplete;
    }

    public function setIsComplete(bool $isComplete): static
    {
        $this->isComplete = $isComplete;

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

    public function getInvoiceTask(): ?InvoiceTask
    {
        return $this->invoiceTask;
    }

    public function setInvoiceTask(?InvoiceTask $invoiceTask): static
    {
        // unset the owning side of the relation if necessary
        if ($invoiceTask === null && $this->invoiceTask !== null) {
            $this->invoiceTask->setInvoice(null);
        }

        // set the owning side of the relation if necessary
        if ($invoiceTask !== null && $invoiceTask->getInvoice() !== $this) {
            $invoiceTask->setInvoice($this);
        }

        $this->invoiceTask = $invoiceTask;

        return $this;
    }

    public function getChecksumPdf(): ?string
    {
        return $this->checksumPdf;
    }

    public function setChecksumPdf(?string $checksumPdf): static
    {
        $this->checksumPdf = $checksumPdf;

        return $this;
    }

    public function getChecksumXml(): ?string
    {
        return $this->checksumXml;
    }

    public function setChecksumXml(?string $checksumXml): static
    {
        $this->checksumXml = $checksumXml;

        return $this;
    }

    public function getSubstitutesInvoice(): ?self
    {
        return $this->substitutesInvoice;
    }

    public function setSubstitutesInvoice(?self $substitutesInvoice): static
    {
        $this->substitutesInvoice = $substitutesInvoice;

        return $this;
    }

    public function getSubstitutedByInvoice(): ?self
    {
        return $this->substitutedByInvoice;
    }

    public function setSubstitutedByInvoice(?self $substitutedByInvoice): static
    {
        // unset the owning side of the relation if necessary
        if ($substitutedByInvoice === null && $this->substitutedByInvoice !== null) {
            $this->substitutedByInvoice->setSubstitutesInvoice(null);
        }

        // set the owning side of the relation if necessary
        if ($substitutedByInvoice !== null && $substitutedByInvoice->getSubstitutesInvoice() !== $this) {
            $substitutedByInvoice->setSubstitutesInvoice($this);
        }

        $this->substitutedByInvoice = $substitutedByInvoice;

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): static
    {
        $this->cancellationReason = $cancellationReason;

        return $this;
    }

    public function getCancellationDate(): ?DateTime
    {
        return $this->cancellationDate;
    }

    public function setCancellationDate(?DateTime $cancellationDate): static
    {
        $this->cancellationDate = $cancellationDate;

        return $this;
    }

    public function getCancellationData(): ?array
    {
        return $this->cancellationData;
    }

    public function setCancellationData(?array $cancellationData): static
    {
        $this->cancellationData = $cancellationData;

        return $this;
    }

    public function getPaymentDate(): ?DateTime
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(?DateTime $paymentDate): static
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function setAttachment(?Attachment $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getPayment(): ?Receipt
    {
        return $this->payment;
    }

    public function setPayment(?Receipt $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    public function getPeticion(?string $key = null): ?array
    {
        if (null === $peticion = $this->getData('peticion')) {
            return null;
        }

        return null === $key
            ? $peticion
            : ($peticion[$key] ?? null);
    }


    public function getPaymentMethod(): ?string
    {
        $peticion = $this->getPeticion();

        return $peticion['CFDi']['DatosDePago']['metodoDePago'] ?? null;
    }

    public function getCfdi(): ?string
    {
        return $this->cfdi;
    }

    public function setCfdi(?string $cfdi): static
    {
        $this->cfdi = $cfdi;

        return $this;
    }
}
