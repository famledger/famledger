<?php

namespace App\Controller\Admin;

use App\Entity\Attachment;
use App\Entity\Series;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;

use App\Admin\Field\CentAmountField;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Service\EDocService;

class StatementCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EDocService            $eDocService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Statement::class;
    }

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

        /** @var Statement $statement */
        $statement = $context->getEntity()->getInstance();

        $activeSeries = array_map(function (Series $series) {
            return $series->getCode();
        }, $this->em->getRepository(Series::class)->findBy(['isActive' => true]));

        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'         => Crud::PAGE_DETAIL,
            'templateName'     => 'crud/detail',
            'entity'           => $context->getEntity(),
            'transactions'     => $statement->getTransactionsOrdered(),
            'documents'        => $this->em->getRepository(Document::class)->findUnLinked($statement),
            'attachments'      => $this->em->getRepository(Attachment::class)->findPendingAttachments($statement),
            'customerInvoices' => $this->em->getRepository(Invoice::class)->findInvoicesWithoutDocuments($activeSeries),
            'eDocsByType'      => $this->eDocService->getEDocsByType($statement),
        ]));
    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions         = $this->addEntityHistoryAction($actions);
        $createStatement = Action::new('uploadStatement', 'Upload Statement', 'fa fa-upload')
            ->linkToRoute('admin_statement_create')
            ->createAsGlobalAction();

        $consolidateStatement = Action::new('consolidateStatement', 'close', 'fa fa-lock-open')
            ->linkToRoute('admin_statement_consolidate', function (Statement $statement): array {
                return ['statement' => $statement->getId()];
            })
            ->displayIf(fn(Statement $statement) => !$statement->isConsolidated())
            ->setHtmlAttributes(['title' => 'click to consolidate the statement']);

        $unConsolidateStatement = Action::new('unConsolidateStatement', 'open', 'fa fa-lock')
            ->linkToRoute('admin_statement_unConsolidate', function (Statement $statement): array {
                return ['statement' => $statement->getId()];
            })
            ->displayIf(fn(Statement $statement) => $statement->isConsolidated())
            ->setHtmlAttributes(['title' => 'click to un-consolidate the statement']);

        return $actions
            ->add(Crud::PAGE_INDEX, $createStatement)
            ->add(Crud::PAGE_DETAIL, $consolidateStatement)
            ->add(Crud::PAGE_DETAIL, $unConsolidateStatement)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statusChoices = [
            Statement::STATUS_CONSOLIDATED => Statement::STATUS_CONSOLIDATED,
            Statement::STATUS_PENDING      => Statement::STATUS_PENDING,
        ];
        $monthChoices  = [
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
                ->add(ChoiceFilter::new('status')->setChoices($statusChoices))
                ->add(ChoiceFilter::new('month')->setChoices($monthChoices))
                ->add(ChoiceFilter::new('year')->setChoices($yearChoices));
        }

        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('account'),
            IntegerField::new('year'),
            IntegerField::new('month')->setTemplatePath('admin/fields/monthAsString.html.twig'),
            CentAmountField::new('startingBalance'),
            CentAmountField::new('endingBalance'),
            CentAmountField::new('balanceDiff'),
            IntegerField::new('noDeposits', '+ TXs'),
            IntegerField::new('noWithdrawals', '- TXs'),
            AssociationField::new('financialMonth')->onlyOnDetail(),
            AssociationField::new('document')->hideOnIndex(),
            BooleanField::new('status')->hideOnForm()
                ->setTemplatePath('admin/fields/statementStatus.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['account.caption'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/Statement/details.html.twig')
            ->setDefaultSort(['year' => 'DESC', 'month' => 'DESC'])
            ->setFormThemes(['admin/add_statement.html.twig', '@EasyAdmin/crud/form_theme.html.twig'])
            ->setEntityLabelInSingular('Statement')
            ->setEntityLabelInPlural('Statements');
    }

    private function getYearsFromDatabase(): array
    {
        $years = $this->em->getConnection()
            ->executeQuery('SELECT DISTINCT year FROM statement ORDER BY year DESC')
            ->fetchFirstColumn();

        return array_combine($years, $years);
    }
}
