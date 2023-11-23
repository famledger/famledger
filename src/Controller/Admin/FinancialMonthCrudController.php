<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use App\Admin\Field\DocumentItemsField;
use App\Entity\FinancialMonth;
use App\Service\Accounting\AccountingFolderManager;

class FinancialMonthCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly AccountingFolderManager $accountingFolderManager
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FinancialMonth::class;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function detail(AdminContext $context)
    {
        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION,
            ['action' => Action::DETAIL, 'entity' => $context->getEntity()])) {
            throw new ForbiddenActionException($context);
        }

        if (!$context->getEntity()->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }

        $this->container->get(EntityFactory::class)->processFields($context->getEntity(),
            FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(EntityFactory::class)->processActions($context->getEntity(),
            $context->getCrud()->getActionsConfig());

        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'     => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity'       => $context->getEntity(),
        ]));
    }

    public function configureFilters(Filters $filters): Filters
    {
        $monthChoices = [
            'January'   => 1,
            'February'  => 2,
            'March'     => 3,
            'April'     => 4,
            'May'       => 5,
            'June'      => 6,
            'July'      => 7,
            'August'    => 8,
            'September' => 9,
            'October'   => 10,
            'November'  => 11,
            'December'  => 12,
        ];

        $yearChoices = $this->getYearsFromDatabase();
        if (count($yearChoices) > 0) {
            $filters
                ->add(EntityFilter::new('account'))
                ->add(ChoiceFilter::new('month')->setChoices($monthChoices))
                ->add(ChoiceFilter::new('year')->setChoices($yearChoices));
        }


        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            IntegerField::new('year'),
            IntegerField::new('month'),
            TextField::new('status'),
            AssociationField::new('account'),
            AssociationField::new('documents')->onlyOnIndex(),
            AssociationField::new('statement'),
            DocumentItemsField::new('documents')->onlyOnDetail(),
        ];
    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        $createMonths = Action::new('addMonths', 'Add Months')
            ->linkToCrudAction('addMonths')
            ->createAsGlobalAction();

        $refresh = Action::new('refresh', 'Refresh')
            ->linkToCrudAction('refresh');

        $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE);

        if (true) { // TODO: only show if not consolidated
            $actions->add(Crud::PAGE_DETAIL, $refresh);
        }

        return $actions;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/Accounting/details.html.twig')
            ->setDefaultSort(['year' => 'DESC', 'month' => 'DESC'])
            ->setEntityLabelInSingular('Financial Month')
            ->setEntityLabelInPlural('Financial Month');
    }

    private function getYearsFromDatabase(): array
    {
        $years = $this->em->getConnection()
            ->executeQuery('SELECT DISTINCT year FROM statement ORDER BY year DESC')
            ->fetchFirstColumn();

        return array_combine($years, $years);
    }
}