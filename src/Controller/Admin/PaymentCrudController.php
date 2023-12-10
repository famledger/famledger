<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use App\Admin\Field\CentAmountField;
use App\Entity\Receipt;

class PaymentCrudController extends AbstractCrudController
{

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Receipt::class;
    }


    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort(['issueDate' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $statementDetailAction = Action::new('statementDetail', 'View Statement')
            ->setIcon('fa fa-balance-scale')
            ->linkToUrl(function (Receipt $payment) {
                // Assuming 'StatementCrudController' and the 'id' of the Statement entity is used
                return (null === $statementId = $payment->getStatement()?->getId())
                    ? ''
                    : $this->adminUrlGenerator
                        ->setController(StatementCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($statementId)
                        ->generateUrl();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $statementDetailAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                $adminUrlGenerator = $this->adminUrlGenerator;

                return $action
                    ->linkToUrl(function ($entity) use ($adminUrlGenerator) {
                        return $adminUrlGenerator
                            ->setController(InvoiceCrudController::class)
                            ->setAction(Action::DETAIL)
                            ->setEntityId($entity->getId())
                            ->generateUrl();
                    })
                    ->setIcon('fa fa-eye')->setLabel('');
            })
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('series');
        yield NumberField::new('number');
        yield TextField::new('recipientRFC');
        yield TextField::new('recipientName');
        yield CollectionField::new('invoicesList')
            ->setTemplatePath('admin/fields/invoicesList.html.twig')
            ->hideOnForm();
        yield DateField::new('paymentDate');
        yield DateField::new('issueDate');
        yield CentAmountField::new('amount');
    }
}
