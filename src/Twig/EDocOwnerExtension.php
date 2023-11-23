<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\EDoc;

class EDocOwnerExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator $urlGenerator
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('edoc_owner_detail_url', [$this, 'edocOwnerDetailUrl'], ['is_safe' => ['html']]),
        ];
    }

    public function edocOwnerDetailUrl(EDoc $eDoc): string
    {
        $url = $this->urlGenerator
            ->setController('App\\Controller\\Admin\\' . $eDoc->getOwnerType() . 'CrudController')
            ->setAction(Action::DETAIL)
            ->setEntityId($eDoc->getOwnerId())
            ->generateUrl();

        $identifier = $eDoc->getOwnerType() . '::' . $eDoc->getOwnerId();

        return sprintf('<a href="%s" title="%s">%s</a>',
            $url,
            $identifier,
            $eDoc->getOwnerKey() ?? $identifier
        );
    }
}