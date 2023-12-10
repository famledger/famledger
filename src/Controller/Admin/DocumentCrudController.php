<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

use App\Admin\Field\CentAmountField;
use App\Admin\Field\DocumentPathField;
use App\Constant\DocumentType;
use App\Entity\Document;
use App\Service\QueryHelper;

class DocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly QueryHelper $queryHelper,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        $downloadDocument = Action::new('downloadDocument', '', 'fa fa-file-pdf text-success')
            ->linkToRoute('admin_document_download', function (Document $document) {
                return [
                    'document' => $document->getId()
                ];
            });

        $financialMonth = Action::new('financialMonth', 'Financial Month', 'fa fa-calendar')
            ->linkToRoute('admin', function (Document $entity) {
                return [
                    'crudAction'         => Crud::PAGE_DETAIL,
                    'crudControllerFqcn' => FinancialMonthCrudController::class,
                    'entityId'           => $entity->getFinancialMonth()->getId(),
                ];
            })
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_DETAIL, $financialMonth)
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

    public function configureFilters(Filters $filters): Filters
    {
        $filters
            ->add(NumericFilter::new('amount'))
            ->add(TextFilter::new('filename'))
            ->add(ChoiceFilter::new('type')
                ->setChoices($this->queryHelper->getPropertyOptions(Document::class, 'type')));

        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            ChoiceField::new('typeString')
                ->setChoices(DocumentType::getOptions())
                ->renderAsNativeWidget()
                ->allowMultipleChoices(false),
            IntegerField::new('year'),
            IntegerField::new('month')->setTemplatePath('admin/fields/monthAsString.html.twig'),
            AssociationField::new('financialMonth'),
            AssociationField::new('transaction')->hideOnForm(),
            AssociationField::new('invoice')->hideOnForm(),
            CentAmountField::new('amount'),
            TextField::new('checksum')->onlyOnDetail(),
            IntegerField::new('sequenceNo'),
            TextField::new('filename'),
            TextField::new('displayFilename')->hideOnForm(),
            DocumentPathField::new('id')->onlyOnDetail(),
            BooleanField::new('isConsolidated')->hideOnForm(),
            DateField::new('created')->hideOnForm()->hideOnIndex(),
            ArrayField::new('specs')->hideOnIndex()->hideOnForm()
                ->setTemplatePath('admin/fields/array.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['created' => 'DESC'])
            ->setSearchFields(['type', 'filename'])
            ->showEntityActionsInlined();
    }
}
