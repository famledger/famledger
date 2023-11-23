<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

use App\Admin\Field\EDocOwnerField;
use App\Entity\EDoc;
use App\Service\QueryHelper;

class EDocCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly QueryHelper $queryHelper,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EDoc::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('eDoc')
            ->setEntityLabelInPlural('eDocs');
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

    public function configureFilters(Filters $filters): Filters
    {
        $filters
            ->add(TextFilter::new('filename'))
            ->add(ChoiceFilter::new('ownerType')
                ->setChoices($this->queryHelper->getPropertyOptions(EDoc::class, 'ownerType')))
            ->add(ChoiceFilter::new('type')
                ->setChoices($this->queryHelper->getPropertyOptions(EDoc::class, 'type')))
            ->add(ChoiceFilter::new('format')
                ->setChoices($this->queryHelper->getPropertyOptions(EDoc::class, 'format')));

        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('type')->hideOnForm(),
            TextField::new('filename'),
            EDocOwnerField::new('self', 'Owner')->hideOnForm()
                ->setTemplatePath('admin/fields/edocOwner.html.twig'),
            TextField::new('format')->hideOnForm(),
            TextField::new('checksum')->hideOnForm(),
            DateField::new('issueDate'),
            DateField::new('created')->hideOnForm(),
        ];
    }
}
