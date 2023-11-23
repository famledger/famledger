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
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;

use App\Entity\Customer;
use App\Entity\EDoc;
use App\Entity\Property;

class CustomerCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em
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

        $this->container->get(EntityFactory::class)->processFields($context->getEntity(),
            FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(EntityFactory::class)->processActions($context->getEntity(),
            $context->getCrud()->getActionsConfig());

        /** @var Customer $customer */
        $customer = $context->getEntity()->getInstance();

        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'     => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity'       => $context->getEntity(),
            'documents'    => $this->em->getRepository(EDoc::class)->findForEntity($customer),
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
        return [
            TextField::new('rfc'),
            TextField::new('name'),
            AssociationField::new('defaultAddress'),
            CollectionField::new('addresses')
                ->hideOnForm()
                ->renderExpanded(),
            TextareaField::new('comment'),
        ];
    }
}
