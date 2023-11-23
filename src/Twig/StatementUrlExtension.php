<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Controller\Admin\StatementCrudController;
use App\Repository\StatementRepository;

class StatementUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator   $urlGenerator,
        private readonly StatementRepository $statementRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('statement_url', [$this, 'statementUrl'], ['is_safe' => ['html']]),
        ];
    }

    public function statementUrl(string $year, string $month): string
    {
        $statement = $this->statementRepository->findOneBy(['year' => $year, 'month' => $month]);

        return null === $statement
            ? ''
            : $this->urlGenerator
                ->setController(StatementCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($statement->getId())
                ->generateUrl();
    }
}