<?php

namespace App\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\EDoc;

class EAUrlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ea_url', [$this, 'eaUrl'], ['is_safe' => ['html']]),
        ];
    }

    public function eaUrl($entity, string $action): string
    {
        $shortClassName = basename(str_replace('\\', '/', get_class($entity)));

        $shortClassName = $shortClassName === 'Attachment' ? 'Document' : $shortClassName;

        return sprintf('/admin?crudAction=%s&crudControllerFqcn=App\Controller\Admin\%sCrudController&entityId=%d',
            $action,
            $shortClassName,
            $entity->getId()
        );
    }
}