<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use App\Entity\FamilyMember;

class FamilyMemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FamilyMember::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Family member')
            ->setEntityLabelInPlural('Family member')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->disable(Action::BATCH_DELETE);
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('icon')->setTemplatePath('admin/fields/familyMemberIcon.html.twig'),
            TextField::new('slug'),
            TextField::new('firstname'),
            TextField::new('lastname'),
        ];
    }
}
