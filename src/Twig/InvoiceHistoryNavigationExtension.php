<?php

namespace App\Twig;

use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Repository\InvoiceRepository;

class InvoiceHistoryNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('invoice_history_navigation', [$this, 'invoiceNavigation'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function invoiceNavigation(?string $currentYear): string
    {
        $years = $this->invoiceRepository->getInvoiceYears();

        // Generate navigation links
        $links = array_map(function ($year) use ($currentYear) {
            // Check if the year matches the provided year
            if ($year === (int)$currentYear) {
                // Format as gray text without a hyperlink
                return sprintf('<span style="color: gray; border-top: 1px solid gray;">%s</span>',
                    $year
                );
            } else {
                // Regular hyperlink format
                return $this->getHistoryYearUrl($year);
            }
        }, $years);

        return implode(' &middot; ', $links);
    }

    private function getHistoryYearUrl(string $year): string
    {
        return sprintf('<a href="%s" title="invoices from %s">%s</a>',
            "/admin?routeName=admin_invoice_history&year=$year",
            $year,
            $year
        );
    }
}
