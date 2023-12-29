<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
class TaxNotice extends Attachment
{
    #[ORM\Column(length: 24, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $captureLine = null;

    #[ORM\OneToOne(inversedBy: 'taxNotice')]
    private ?TaxPayment $taxPayment = null;

    public function setSpecs(?array $specs): static
    {
        parent::setSpecs($specs);
        if (null !== $specs) {
            $this->setCaptureLine($specs['captureLine'] ?? null);
        }

        return $this;
    }

    public function setCaptureLine(?string $captureLine): static
    {
        $this->captureLine = $captureLine;

        return $this;
    }

    public function getCaptureLine(): ?string
    {
        return $this->captureLine;
    }

    public function getStatementFromSelf(): ?static
    {
        return $this;
    }

    public function getTaxPaymentFromSelf(): ?static
    {
        return $this;
    }

    public function getTaxPayment(): ?TaxPayment
    {
        return $this->taxPayment;
    }

    public function setTaxPayment(?TaxPayment $taxPayment): static
    {
        $this->taxPayment = $taxPayment;

        return $this;
    }
}