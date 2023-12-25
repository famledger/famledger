<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
class TaxPayment extends Document
{
    #[ORM\OneToOne(mappedBy: 'taxPayment')]
    private ?TaxNotice $taxNotice = null;

    #[ORM\Column(length: 24, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $captureLine = null;

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

    public function getTaxNotice(): ?TaxNotice
    {
        return $this->taxNotice;
    }

    public function setTaxNotice(?TaxNotice $taxNotice): static
    {
        // unset the owning side of the relation if necessary
        if ($taxNotice === null and $this->taxNotice !== null) {
            $this->taxNotice->setTaxPayment(null);
        }

        // set the owning side of the relation if necessary
        if ($taxNotice !== null and $taxNotice->getTaxPayment() !== $this) {
            $taxNotice->setTaxPayment($this);
            $month = $taxNotice->getMonth();
            $year  = $taxNotice->getYear();
            $this->setFilename($month === 13
                ? "Pago Impuesto Anual $year.pdf"
                : sprintf('Pago Impuesto Provisional %s-%02d.pdf', $year, $month)
            );
        }

        $this->taxNotice = $taxNotice;

        return $this;
    }
}