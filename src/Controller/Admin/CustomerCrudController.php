<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;

use App\Entity\Customer;
use App\Service\EDocService;

class CustomerCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EDocService            $eDocService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Customer::class;
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

        try {
            $this->container->get(EntityFactory::class)->processFields($context->getEntity(),
                FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));
        } catch (\Throwable $e) {
            $a = 1;
        }
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(EntityFactory::class)->processActions($context->getEntity(),
            $context->getCrud()->getActionsConfig());

        /** @var Customer $customer */
        $customer = $context->getEntity()->getInstance();

        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'     => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity'       => $context->getEntity(),
            'eDocsByType'  => $this->eDocService->getEDocsByType($customer),
        ]));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/Customer/details.html.twig')
            ->setEntityLabelInSingular('Customer')
            ->setEntityLabelInPlural('Customers');
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
        yield TextField::new('rfc');
        yield TextField::new('name');
        yield ColorField::new('color');
        yield AssociationField::new('defaultAddress');
        yield TextareaField::new('comment');
        yield BooleanField::new('isActive')->setLabel('active');

        yield FormField::addPanel('Accounts')->collapsible()->setIcon('fa fa-bank');
        yield CollectionField::new('accounts')
            ->setFormTypeOptions(['by_reference' => false, 'required' => true])
            ->setEntryIsComplex()
            ->useEntryCrudForm(AccountCrudController::class)
            ->hideOnIndex()
            ->renderExpanded();
        yield FormField::addPanel('Addresses')->collapsible()->setIcon('fa fa-address-card');
        yield CollectionField::new('addresses')
            ->setFormTypeOptions(['by_reference' => false, 'required' => true])
            ->setEntryIsComplex()
            ->useEntryCrudForm(AddressCrudController::class)
            ->hideOnIndex()
            ->renderExpanded();

        yield FormField::addPanel('Emails')->collapsible()->setIcon('fa fa-envelope');
        yield CollectionField::new('emails')
            ->setFormTypeOptions(['by_reference' => false, 'required' => true])
            ->setEntryIsComplex()
            ->useEntryCrudForm(EmailCrudController::class)
            ->hideOnIndex();
    }
}
