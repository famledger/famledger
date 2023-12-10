<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Admin\Field\CentAmountField;
use App\Entity\Invoice;
use App\Entity\ReceiptTask;
use App\Entity\Series;
use App\Repository\TransactionRepository;

class ReceiptTaskCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack           $requestStack
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ReceiptTask::class;
    }

    public function build(
        AdminUrlGenerator      $adminUrlGenerator,
        Request                $request,
        EntityManagerInterface $em,
        TransactionRepository  $transactionRepository,
    ): RedirectResponse {

        try {
            $em->getConnection()->beginTransaction();
            $transaction        = $transactionRepository->find($request->query->get('transaction'));
            $beneficiaryAccount = $transaction->getStatement()->getAccount();
            $invoices           = $transaction->getPaidInvoices();
            $customer           = $transaction->getCustomer();
            $receiptTask        = new ReceiptTask();
            foreach ($invoices as $invoice) {
                $receiptTask->addInvoice($invoice);
            }
            $series = $em->getRepository(Series::class)->findOneBy(['source' => 'RECEIPT', 'isActive' => true]);
            $receiptTask
                ->setSeries($series)
                ->setAmount($transaction->getAmount())
                ->setCustomer($customer)
                ->setOriginatorAccount($customer->getDefaultAccount())
                ->setBeneficiaryAccount($beneficiaryAccount)
                ->setConcept('Pago de facturas: ' . implode(', ', array_map(function (Invoice $invoice) {
                        return (string)$invoice;
                    }, $invoices)));

            $em->persist($receiptTask);
            $em->flush();
            $em->getConnection()->commit();

            return $this->redirect($adminUrlGenerator
                ->setController(ReceiptTaskCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($receiptTask->getId())
                ->generateUrl()
            );
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());

            return $this->redirect();
        }
    }

//    public function preview(
//        AdminContext   $adminContext,
//        ReceiptBuilder $receiptBuilder,
//        Request        $request,
//    ): Response {
//
//        try {
//            $receiptTask = $adminContext->getEntity()->getInstance();
//        } catch (Exception $e) {
//            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
//        }
//
//        return $this->render('admin/ReceiptTask/preview.html.twig', [
//            'receiptTask' => $receiptTask,
//            'request'     => $receiptBuilder->buildRequestFromTask($receiptTask, $totalAmount),
//        ]);
//    }

    use EntityHistoryButtonTrait;

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);
        $execute = Action::new('execute', 'create receipt', 'fa fa-play')
            ->linkToCrudAction('execute')
            ->displayIf(fn(ReceiptTask $entity) => !($entity->getStatus() === ReceiptTask::STATUS_COMPLETED));
        $preview = Action::new('preview', 'preview', 'fa fa-magnifying-glass')
            ->linkToCrudAction('preview')
            ->displayIf(fn(ReceiptTask $entity) => !($entity->getStatus() === ReceiptTask::STATUS_COMPLETED));

        return $actions
            ->add(Crud::PAGE_INDEX, $execute)
            ->add(Crud::PAGE_DETAIL, $execute)
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
                    ->displayIf(fn(ReceiptTask $entity) => !$entity->isCompleted());
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(fn(ReceiptTask $entity) => !$entity->isCompleted());
            })
            ->update(Crud::PAGE_DETAIL, Action::EDIT, function (Action $action) {
                return $action->displayIf(fn(ReceiptTask $entity) => !$entity->isCompleted());
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

            AssociationField::new('customer')->hideOnForm(),
            AssociationField::new('series')->hideOnForm(),
            AssociationField::new('receipt')->hideOnForm(),
            AssociationField::new('originatorAccount')->hideOnForm()->hideOnIndex(),
            AssociationField::new('beneficiaryAccount')->hideOnForm()->hideOnIndex(),
            CentAmountField::new('amount')->hideOnForm(),
            TextField::new('concept')->hideOnForm(),

            DateField::new('lastExecuted')->hideOnForm(),
            TextField::new('status')->hideOnForm()
                ->setTemplatePath('admin/fields/invoiceTaskStatus.html.twig'),
            TextareaField::new('receiptTemplate')->hideOnIndex()
                ->setTemplatePath('admin/fields/jsonString.html.twig'),
            BooleanField::new('liveMode')->hideOnForm()
                ->setTemplatePath('admin/fields/liveModeStatus.html.twig'),
            CollectionField::new('invoices')
                ->renderExpanded()
                ->hideOnForm()->hideOnIndex(),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(
                fn(?ReceiptTask $receiptTask, ?string $pageName) => $receiptTask
                    ? 'Receipt Task #' . $receiptTask->getId() . ' - ' . $receiptTask->getConcept()
                    : 'Receipt Task'
            )
            ->setEntityLabelInPlural('Receipt Tasks')
            ->showEntityActionsInlined();
    }
}
