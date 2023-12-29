<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use App\Admin\Field\CentAmountField;
use App\Entity\Document;
use App\Entity\TaxNotice;

class TaxNoticeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TaxNotice::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $downloadDocument = Action::new('downloadDocument', '', 'fa fa-file-pdf text-success')
            ->linkToRoute('admin_document_download', function (Document $document) {
                return [
                    'document' => $document->getId()
                ];
            });

        $rebuildDocument = Action::new('rebuildDocument', '', 'fa fa-refresh text-warning')
            ->linkToRoute('admin_document_rebuild', function (Document $document) {
                return [
                    'document' => $document->getId()
                ];
            });

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_DETAIL, $rebuildDocument)
            ->add(Crud::PAGE_DETAIL, $downloadDocument)
            ->add(Crud::PAGE_INDEX, $downloadDocument)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-pencil')->setLabel('');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel('');
            })
            //->remove(Crud::PAGE_INDEX, Action::NEW)
            ->reorder(Crud::PAGE_INDEX, ['downloadDocument', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('year'),
            IntegerField::new('month')->setTemplatePath('admin/fields/monthAsString.html.twig'),
            TextField::new('taxPaymentFromSelf', 'Tax payment')->setTemplatePath('admin/fields/taxPayment.html.twig'),
            TextField::new('statementFromSelf',
                'Statement')->hideOnForm()->setTemplatePath('admin/fields/taxNoticeStatement.html.twig'),
            AssociationField::new('financialMonth'),
            AssociationField::new('statement')->hideOnIndex(),
            CentAmountField::new('amount'),
            TextField::new('checksum')->onlyOnDetail(),
            TextAreaField::new('comment')->hideOnIndex(),
            DateField::new('created')->hideOnForm()->hideOnIndex(),
            ArrayField::new('specs')->hideOnIndex()->hideOnForm()
                ->setTemplatePath('admin/fields/array.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['year' => 'DESC', 'month' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Tax Notices/Payments')
            ->setPaginatorPageSize(25)
            ->setSearchFields(['filename', 'month', 'year'])
            ->overrideTemplate('crud/detail', 'admin/Document/details.html.twig')
            ->overrideTemplate('crud/index', 'admin/TaxPayment/index.html.twig')
            ->showEntityActionsInlined();
    }
}
