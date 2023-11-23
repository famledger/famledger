<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

trait EntityHistoryButtonTrait
{
    public function addEntityHistoryAction(Actions $actions): Actions
    {
        $customButton = Action::new('entity_history', 'History')
            ->setIcon('fa fa-history')
            ->linkToRoute('admin_entity_history', function ($entity) {
                return [
                    'class' => get_class($entity),
                    'id'    => $entity->getId(),
                ];
            });

        return $actions
            ->add(Crud::PAGE_DETAIL, $customButton);
    }
}
