<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

use App\Admin\Field\CentAmountField;
use App\Constant\PaymentForm;
use App\Constant\PaymentMethod;
use App\Constant\UsoCFDi;
use App\Entity\InvoiceTask;
use App\Entity\Series;
use App\Service\Invoice\InvoiceBuilder;
use App\Service\Invoice\InvoiceSynchronizer;
use App\Service\Invoice\ReceiptBuilder;

class InvoiceTaskCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack           $requestStack
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return InvoiceTask::class;
    }

    public function preview(
        AdminContext   $adminContext,
        ReceiptBuilder $receiptBuilder,
        Request        $request,
    ): Response {

        try {
            $receiptTask = $adminContext->getEntity()->getInstance();
        } catch (Exception $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->render('admin/InvoiceTask/preview.html.twig', [
            'invoiceTask' => $receiptTask,
            'request'     => $receiptBuilder->buildRequestFromTask($receiptTask),
        ]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function execute(
        AdminUrlGenerator      $adminUrlGenerator,
        AdminContext           $adminContext,
        InvoiceBuilder         $invoiceBuilder,
        InvoiceSynchronizer    $invoiceSynchronizer,
        Request                $request,
        EntityManagerInterface $em
    ): RedirectResponse {

        $invoiceTask = $adminContext->getEntity()->getInstance();
        try {
            $em->getConnection()->beginTransaction();
            $invoice = $invoiceBuilder->createInvoice($invoiceTask);
            $em->flush();
            $em->getConnection()->commit();

            // invoice details are fetched after the invoice is created
            $invoiceSynchronizer->syncInvoiceDetails($invoice);
            $em->flush();

        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirect($adminUrlGenerator
            ->setController(InvoiceTaskCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoiceTask->getId())
            ->generateUrl()
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function clone(
        AdminUrlGenerator      $adminUrlGenerator,
        AdminContext           $adminContext,
        Request                $request,
        EntityManagerInterface $em
    ): RedirectResponse {

        $invoiceTask = $adminContext->getEntity()->getInstance();
        try {
            $em->getConnection()->beginTransaction();
            $newInvoiceTask = clone $invoiceTask;
            $em->persist($newInvoiceTask);
            $em->flush();
            $em->getConnection()->commit();
            $invoiceTask = $newInvoiceTask;
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

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
        $actions = $this->addEntityHistoryAction($actions);
        $clone   = Action::new('clone', 'clone invoice task', 'fa fa-clone')
            ->linkToCrudAction('clone')
            ->displayIf(fn(InvoiceTask $entity) => ($entity->getStatus() === InvoiceTask::STATUS_COMPLETED));
        $execute = Action::new('execute', 'create invoice', 'fa fa-play')
            ->linkToCrudAction('execute')
            ->displayIf(fn(InvoiceTask $entity) => !($entity->getStatus() === InvoiceTask::STATUS_COMPLETED));
        $preview = Action::new('preview', 'preview', 'fa fa-magnifying-glass')
            ->linkToCrudAction('preview')
            ->displayIf(fn(InvoiceTask $entity) => !($entity->getStatus() === InvoiceTask::STATUS_COMPLETED));

        return $actions
            ->add(Crud::PAGE_INDEX, $execute)
            ->add(Crud::PAGE_INDEX, $clone)
            ->add(Crud::PAGE_DETAIL, $execute)
            ->add(Crud::PAGE_DETAIL, $clone)
            ->add(Crud::PAGE_DETAIL, $preview)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(Crud::PAGE_EDIT, Action::INDEX, function (Action $action) {
                return $action->setIcon('fa fa-arrow-left')->setLabel('back to listing');
            })
//            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-pencil')
                    ->displayIf(fn(InvoiceTask $entity) => !$entity->isCompleted());
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(fn(InvoiceTask $entity) => !$entity->isCompleted());
            })
            ->update(Crud::PAGE_DETAIL, Action::EDIT, function (Action $action) {
                return $action->displayIf(fn(InvoiceTask $entity) => !$entity->isCompleted());
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $series = $this->em->getRepository(Series::class)->getActiveSeries();

        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('substitutesInvoice')
                ->setFormTypeOptions([
                    'query_builder' => function (EntityRepository $er) use ($series) {
                        $qb = $er->createQueryBuilder('i');
                        $qb
                            ->where($qb->expr()->in('i.series', $series))
                            ->orderBy('i.series', 'DESC')
                            ->addOrderBy('i.number', 'DESC');

                        return $qb;
                    }
                ]),

            AssociationField::new('customer'),
            AssociationField::new('invoiceSchedule')->hideOnForm(),
            AssociationField::new('series'),
            ChoiceField::new('taxCategory')->setChoices([
                'Persona Fisica'        => 'Arrendamiento PF',
                'Persona Moral'         => 'Arrendamiento PM',
                'Actividad Empresarial' => 'Arrendamiento AE',
                'Extranjero'            => 'Extranjero',
            ])->hideOnIndex(),
            ChoiceField::new('invoiceUsage')->setChoices(UsoCFDi::getOptions())->hideOnIndex(),
            ChoiceField::new('paymentMethod')->setChoices(PaymentMethod::getOptions())->hideOnIndex(),
            ChoiceField::new('paymentForm')->setChoices(PaymentForm::getOptions())->hideOnIndex(),
            AssociationField::new('invoice')->hideOnForm(),
            CentAmountField::new('amount'),
            IntegerField::new('year'),
            IntegerField::new('month')->setTemplatePath('admin/fields/monthAsString.html.twig'),
            TextField::new('concept')->hideOnForm(),
            DateField::new('lastExecuted')->hideOnForm(),
            TextField::new('status')->hideOnForm()
                ->setTemplatePath('admin/fields/invoiceTaskStatus.html.twig'),
            TextareaField::new('invoiceTemplate')->hideOnIndex()
                ->setTemplatePath('admin/fields/jsonString.html.twig'),
            BooleanField::new('liveMode')->hideOnForm()
                ->setTemplatePath('admin/fields/liveModeStatus.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['year' => 'DESC', 'month' => 'DESC'])
            ->setEntityLabelInSingular(
                fn(?InvoiceTask $invoiceTask, ?string $pageName) => $invoiceTask
                    ? 'Invoice Task #' . $invoiceTask->getId() . ' - ' . $invoiceTask->getConcept()
                    : 'Invoice Task'
            )
            ->setEntityLabelInPlural('Invoice Tasks')
            ->showEntityActionsInlined();
    }
}
