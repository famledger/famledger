<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Controller\Admin\PropertyCrudController;
use App\Entity\Property;
use App\Repository\PropertyRepository;

class PropertyNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator  $urlGenerator,
        private readonly PropertyRepository $propertyRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('property_navigation', [$this, 'propertyNavigation'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function propertyNavigation(Property $property): string
    {
        $properties = $this->propertyRepository->findAll();

        // Sort statements
        usort($properties, function ($a, $b) {
            return $a->getSlug() <=> $b->getSlug();
        });

        // Generate navigation links
        $links = array_map(function ($prop) use ($property) {
            // Check if the current statement is the same as the provided statement
            if ($prop->getId() === $property->getId()) {
                // Format as gray text without a hyperlink
                return sprintf('<span style="color: gray; border-top: 1px solid gray;">%s</span>', $prop->getSlug());
            } else {
                // Regular hyperlink format
                return $this->getPropertyUrl($prop);
            }
        }, $properties);

        return implode(' &middot; ', $links);
    }

    private function getPropertyUrl(Property $property): string
    {
        $url = $this->urlGenerator
            ->setController(PropertyCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($property->getId())
            ->generateUrl();

        return sprintf('<a href="%s" title="%s">%s</a>',
            $url,
            $property->getCaption(),
            $property->getSlug()
        );
    }
}
