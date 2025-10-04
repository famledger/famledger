<?php

namespace App\Controller\Admin;

use App\Entity\Contract;
use App\Entity\Customer;
use App\Entity\Property;
use App\Service\EDocService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class ContractCrudController extends AbstractCrudController
{
    use EntityHistoryButtonTrait;

    public function __construct(
        private readonly EDocService $eDocService,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Contract::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Contract')
            ->setEntityLabelInPlural('Contracts')
            ->setSearchFields(['customer.name', 'customer.rfc', 'property.caption', 'property.slug', 'notaryName'])
            ->setDefaultSort(['endDate' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('customer')
            ->setFormTypeOptions([
                'class'        => Customer::class,
                'choice_label' => function (Customer $customer) {
                    return $customer->getRfc() . ' - ' . $customer->getName();
                }
            ]);

        yield AssociationField::new('property')
            ->setFormTypeOptions([
                'class'        => Property::class,
                'choice_label' => function (Property $property) {
                    return $property->getSlug() . ' - ' . $property->getCaption();
                }
            ]);

        yield DateField::new('startDate', 'Start Date');
        yield DateField::new('endDate', 'End Date');
        yield DateField::new('signingDate', 'Signing Date')->hideOnIndex();

        yield MoneyField::new('monthlyAmount', 'Monthly Amount')
            ->setCurrency('MXN')
            ->setStoredAsCents(false);

        yield TextField::new('notaryName', 'Notary Name')->hideOnIndex();
        yield TextareaField::new('notes')->hideOnIndex();
        yield BooleanField::new('isActive', 'Active');

        // Add file upload field for contract PDF
        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield TextField::new('contractFileUpload', 'Contract File')
                ->setTemplatePath('admin/contract/contract_file.html.twig')
                ->formatValue(function ($value, Contract $contract) {
                    return $contract;
                })
                ->hideOnForm()
                ->setVirtual(true);
        }

        // Add calculated field for days until expiration on index
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('daysUntilExpiration', 'Days to Expiry')
                ->formatValue(function ($value, Contract $contract) {
                    $days = $contract->getDaysUntilExpiration();
                    if ($days < 0) {
                        return '<span class="badge badge-danger">Expired ' . abs($days) . ' days ago</span>';
                    } elseif ($days <= 30) {
                        return '<span class="badge badge-warning">' . $days . ' days</span>';
                    } elseif ($days <= 90) {
                        return '<span class="badge badge-info">' . $days . ' days</span>';
                    } else {
                        return '<span class="badge badge-success">' . $days . ' days</span>';
                    }
                });

            yield TextField::new('contractFile', 'File')
                ->setTemplatePath('admin/contract/contract_pdf_link.html.twig')
                ->formatValue(function ($value, ?Contract $contract) {
                    return $contract;
                });
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        $renewAction = Action::new('renew', 'Renew Contract', 'fas fa-redo')
            ->linkToCrudAction('renewContract')
            ->displayIf(function (Contract $contract) {
                return $contract->getIsActive();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $renewAction)
            ->add(Crud::PAGE_INDEX, $renewAction)
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

    public function renewContract(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): \Symfony\Component\HttpFoundation\Response
    {
        /** @var Contract $originalContract */
        $originalContract = $context->getEntity()->getInstance();

        // Create new contract based on the original
        $newContract = new Contract();
        $newContract->setTenant($originalContract->getTenant());
        $newContract->setCustomer($originalContract->getCustomer());
        $newContract->setProperty($originalContract->getProperty());
        $newContract->setMonthlyAmount($originalContract->getMonthlyAmount());
        $newContract->setNotaryName($originalContract->getNotaryName());
        $newContract->setNotes($originalContract->getNotes());
        $newContract->setIsActive(true);

        // Calculate new dates (increment by the contract duration)
        $originalStart = $originalContract->getStartDate();
        $originalEnd = $originalContract->getEndDate();

        if ($originalStart && $originalEnd) {
            $duration = $originalStart->diff($originalEnd);
            $newStartDate = clone $originalEnd;
            $newStartDate->add(new \DateInterval('P1D')); // Start the day after the original ends

            $newEndDate = clone $newStartDate;
            $newEndDate->add($duration);

            $newContract->setStartDate($newStartDate);
            $newContract->setEndDate($newEndDate);
        }

        // Clear fields that should be null for new contract
        $newContract->setSigningDate(null);

        // Persist the new contract
        $this->entityManager->persist($newContract);
        $this->entityManager->flush();

        // Redirect to the new contract's edit page
        $url = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($newContract->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
