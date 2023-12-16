<?php

namespace App\Twig;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Controller\Admin\StatementCrudController;
use App\Entity\Statement;
use App\Repository\StatementRepository;
use App\Service\MonthConverter;

class StatementNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator   $urlGenerator,
        private readonly StatementRepository $statementRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('statement_navigation', [$this, 'statementNavigation'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function statementNavigation(Statement $statement, ?int $range = 3): string
    {
        // fetch all statements for the months up to the range months before and after the current statement
        $currentDate = new DateTime(sprintf('%d-%d-01', $statement->getYear(), $statement->getMonth()));

        // Calculate start and end dates
        $startDate = (clone $currentDate)->modify("-{$range} months");
        $endDate   = (clone $currentDate)->modify("+{$range} months");

        // Fetch statements within the range
        $statements = $this->statementRepository->findByDateRange($statement->getAccount(), $startDate, $endDate);

        // Sort statements
        usort($statements, function ($a, $b) {
            return [$a->getYear(), $a->getMonth()] <=> [$b->getYear(), $b->getMonth()];
        });

        // Generate navigation links
        $links = array_map(function ($stmt) use ($statement) {
            // Check if the current statement is the same as the provided statement
            if ($stmt->getId() === $statement->getId()) {
                // Format as gray text without a hyperlink
                return sprintf('<span style="color: gray; border-top: 1px solid gray;">%s</span>',
                    MonthConverter::fromNumericMonth($stmt->getMonth(), true)
                );
            } else {
                // Regular hyperlink format
                return $this->getStatementUrl($stmt);
            }
        }, $statements);

        return implode(' &middot; ', $links);
    }

    private function getStatementUrl(Statement $statement): string
    {
        $url = $this->urlGenerator
            ->setController(StatementCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($statement->getId())
            ->generateUrl();

        return sprintf('<a href="%s" title="%s">%s</a>',
            $url,
            MonthConverter::fromNumericMonth($statement->getMonth(), false) . ' ' . $statement->getYear(),
            MonthConverter::fromNumericMonth($statement->getMonth(), true)
        );
    }
}
