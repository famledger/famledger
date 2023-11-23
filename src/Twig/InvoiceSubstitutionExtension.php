<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\Invoice;

class InvoiceSubstitutionExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator $urlGenerator
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('invoice_substitution_link', [$this, 'invoiceSubstitutionLink'], ['is_safe' => ['html']]),
        ];
    }

    public function invoiceSubstitutionLink(Invoice $invoice): string
    {
        if (null !== $substitutedInvoice = $invoice->getSubstitutedByInvoice()) {
            return $this->renderLink($invoice, 'substituted by ' . $substitutedInvoice->__toString(), 'text-danger');
        }
        if (null !== $substitutesInvoice = $invoice->getSubstitutesInvoice()) {
            return $this->renderLink($invoice, 'substitutes ' . $substitutesInvoice->__toString(), 'text-success');
        }

        return '';
    }

    private function renderLink(Invoice $invoice, string $title, string $class): string
    {
        $url = $this->urlGenerator
            ->setController('App\\Controller\\Admin\\InvoiceCrudController')
            ->setAction(Action::DETAIL)
            ->setEntityId($invoice->getId())
            ->generateUrl();

        return sprintf('<a href="%s" title="%s" class="%s"><i class="fa fa-exchange-alt"></i></a>',
            $url,
            $title,
            $class
        );
    }
}