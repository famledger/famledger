<?php

namespace App\Twig;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Controller\Admin\FinancialMonthCrudController;
use App\Entity\FinancialMonth;
use App\Repository\FinancialMonthRepository;
use App\Service\MonthConverter;

class FinancialMonthNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator        $urlGenerator,
        private readonly FinancialMonthRepository $financialMonthRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('financial_month_navigation', [$this, 'financialMonthNavigation'],
                ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function financialMonthNavigation(FinancialMonth $financialMonth, ?int $range = 3): string
    {
        // fetch all statements for the months up to the range months before and after the current statement
        $currentDate = new DateTime(sprintf('%d-%d-01', $financialMonth->getYear(), $financialMonth->getMonth()));

        // Calculate start and end dates
        $startDate = (clone $currentDate)->modify("-{$range} months");
        $endDate   = (clone $currentDate)->modify("+{$range} months");

        $financialMonths = $this->financialMonthRepository->findByDateRange($financialMonth->getAccount(), $startDate, $endDate);

        // Sort statements
        usort($financialMonths, function ($a, $b) {
            return [$a->getYear(), $a->getMonth()] <=> [$b->getYear(), $b->getMonth()];
        });

        // Generate navigation links
        $links = array_map(function ($fm) use ($financialMonth) {
            // Check if the current statement is the same as the provided statement
            if ($fm->getId() === $financialMonth->getId()) {
                // Format as gray text without a hyperlink
                return sprintf('<span style="color: gray; border-top: 1px solid gray;">%s</span>',
                    MonthConverter::fromNumericMonth($fm->getMonth(), true)
                );
            } else {
                // Regular hyperlink format
                return $this->getUrl($fm);
            }
        }, $financialMonths);

        return implode(' &middot; ', $links);

    }

    private function getUrl(FinancialMonth $financialMonth): string
    {
        $url = $this->urlGenerator
            ->setController(FinancialMonthCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($financialMonth->getId())
            ->generateUrl();

        return sprintf('<a href="%s" title="%s">%s</a>',
            $url,
            MonthConverter::fromNumericMonth($financialMonth->getMonth(), false) . ' ' . $financialMonth->getYear(),
            MonthConverter::fromNumericMonth($financialMonth->getMonth(), true)
        );
    }
}
