<?php

namespace App\Controller\Admin;

use Angle\CFDI\Catalog\RegimeType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

use App\Constant\PaymentForm;
use App\Constant\PaymentMethod;
use App\Constant\UsoCFDi;
use App\Entity\InvoiceSchedule;
use App\Service\InvoiceTaskBuilder;

class InvoiceScheduleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InvoiceSchedule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Invoice Schedule')
            ->setEntityLabelInPlural('Invoice Schedule')
            ->showEntityActionsInlined()
            ->setDefaultSort(['isActive' => 'DESC', 'property' => 'ASC']);
    }

    public function schedule(
        AdminUrlGenerator  $adminUrlGenerator,
        AdminContext       $adminContext,
        InvoiceTaskBuilder $invoiceTaskBuilder
    ): RedirectResponse {
        $invoiceSchedule = $adminContext->getEntity()->getInstance();
        $invoiceTask     = $invoiceTaskBuilder->create($invoiceSchedule);

        return $this->redirect($adminUrlGenerator
            ->setController(InvoiceTaskCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoiceTask->getId())
            ->generateUrl()
        );
    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions  = $this->addEntityHistoryAction($actions);
        $schedule = Action::new('schedule', 'schedule a task', 'fa fa-clock')
            ->linkToCrudAction('schedule')
            ->displayIf(fn(InvoiceSchedule $entity) => ($entity->getIsActive()));

        return $actions
            ->add(Crud::PAGE_DETAIL, $schedule)
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
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        $regimes       = RegimeType::listForFormBuilder();
        $regimeChoices = [];
        foreach ($regimes as $name => $id) {
            $regimeChoices[sprintf('%s [%d]', $name, $id)] = $id;
        }

        return [
            AssociationField::new('property'),
            AssociationField::new('customer'),
            ChoiceField::new('regimeType')->setChoices($regimeChoices),
            AssociationField::new('series'),
            IntegerField::new('amount')->setTemplatePath('admin/fields/centAmount.html.twig'),
            ChoiceField::new('taxCategory')->setChoices([
                'Persona Fisica'        => 'Arrendamiento PF',
                'Persona Moral'         => 'Arrendamiento PM',
                'Actividad Empresarial' => 'Arrendamiento AE',
            ]),
            ChoiceField::new('invoiceUsage')->setChoices(UsoCFDi::getOptions()),
            ChoiceField::new('paymentMethod')->setChoices(PaymentMethod::getOptions()),
            ChoiceField::new('paymentForm')->setChoices(PaymentForm::getOptions()),
            TextField::new('frequency'),
            IntegerField::new('monthlyPaymentDay')->setLabel('PayDay')->setLabel('Pay day'),
//            DateField::new('scheduledDate')->setLabel('Scheduled'),
//            DateField::new('nextIssueDate')->setLabel('Next issue'),
            TextField::new('concept'),
            TextareaField::new('invoiceTemplate')->hideOnIndex()
                ->setTemplatePath('admin/fields/jsonString.html.twig'),
            BooleanField::new('isActive')->setLabel('active'),
        ];
    }
}
