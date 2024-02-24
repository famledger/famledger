<?php

namespace App\Twig;

use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Repository\PaymentTransactionRepository;

class PaymentHistoryNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly PaymentTransactionRepository $paymentTransactionRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('payment_history_navigation', [$this, 'paymentHistoryNavigation'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function paymentHistoryNavigation(?string $currentYear): string
    {
        $years = $this->paymentTransactionRepository->getPaymentYears();

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
        return sprintf('<a href="%s" title="payments from %s">%s</a>',
            "/admin?routeName=admin_payment_history&year=$year",
            $year,
            $year
        );
    }
}
