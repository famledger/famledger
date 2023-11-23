<?php

namespace App\Controller\Admin;

use App\Admin\Field\CentAmountField;
use App\Entity\VehicleTax;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class VehicleTaxCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VehicleTax::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/Vehicle/details.html.twig')
            ->setEntityLabelInSingular('Tax Payment')
            ->setEntityLabelInPlural('Tax Payments');
    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-pencil')->setLabel('');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel('');
            });
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('vehicle'),
            CentAmountField::new('amount'),
            DateField::new('paymentDate'),
            DateField::new('nextDueDate'),
        ];
    }
}
