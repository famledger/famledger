<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Controller\Admin\InvoiceCrudController;
use App\Entity\Invoice;
use App\Repository\InvoiceRepository;

class InvoiceNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('invoice_navigation', [$this, 'invoiceNavigation'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function invoiceNavigation(Invoice $invoice, ?int $range = 3): string
    {
        $invoices     = $this->invoiceRepository->findInvoicesForCustomer($invoice->getCustomer());
        $currentIndex = array_search($invoice, $invoices);
        $startIndex   = max(0, $currentIndex - $range);
        $endIndex     = min(count($invoices) - 1, $currentIndex + $range);

        // Slice the array to get the desired range of invoices
        $proximityInvoices = array_slice($invoices, $startIndex, $endIndex - $startIndex + 1);

        // Generate navigation links
        $links = array_map(function ($stmt) use ($invoice) {
            // Check if the current invoice is the same as the provided invoice
            if ($stmt->getId() === $invoice->getId()) {
                // Format as gray text without a hyperlink
                return sprintf('<span style="color: gray; border-top: 1px solid gray;">%s</span>',
                    $invoice
                );
            } else {
                // Regular hyperlink format
                return $this->getInvoiceUrl($stmt);
            }
        }, $proximityInvoices);

        return implode(' &middot; ', $links);
    }

    private function getInvoiceUrl(Invoice $invoice): string
    {
        $url = $this->urlGenerator
            ->setController(InvoiceCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoice->getId())
            ->generateUrl();

        return sprintf('<a href="%s" title="issued %s">%s</a>',
            $url,
            $invoice->getIssueDate()->format('d-m-Y'),
            $invoice
        );
    }
}
