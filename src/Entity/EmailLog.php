<?php

namespace App\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use App\Repository\EmailLogRepository;

#[ORM\Entity(repositoryClass: EmailLogRepository::class)]
class EmailLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'json')]
    private array $emails = [];

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateSent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentStatus = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getEmails(): array
    {
        return $this->emails;
    }

    public function setEmails(array $emails): static
    {
        $this->emails = $emails;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
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

    public function getDateSent(): ?DateTime
    {
        return $this->dateSent;
    }

    public function setDateSent(?DateTime $dateSent): static
    {
        $this->dateSent = $dateSent;

        return $this;
    }

    public function getDocumentStatus(): ?string
    {
        return $this->documentStatus;
    }

    public function setDocumentStatus(?string $documentStatus): static
    {
        $this->documentStatus = $documentStatus;

        return $this;
    }
}
