<?php

namespace App\Controller\Admin;

use DateTime;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CurrencyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Admin\Field\CentAmountField;
use App\Constant\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\Receipt;
use App\Exception\EfClientException;
use App\Form\InvoiceCancelType;
use App\Service\EFClient;
use App\Service\Invoice\InvoiceSynchronizer;
use App\Service\TenantContext;

class InvoiceCrudController extends AbstractCrudController
{

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    use EntityHistoryButtonTrait;

    public function detail(AdminContext $context)
    {
        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION,
            ['action' => Action::DETAIL, 'entity' => $context->getEntity()])) {
            throw new ForbiddenActionException($context);
        }

        if (!$context->getEntity()->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }

        $this->container->get(EntityFactory::class)
            ->processFields($context->getEntity(), FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));

        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));

        $this->container->get(EntityFactory::class)
            ->processActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $invoice    = $context->getEntity()->getInstance();
        $cancelForm = (strtolower($invoice->getStatus()) === InvoiceStatus::VIGENTE)
            ? $this->createInvoiceCancelFrom($invoice)
            : null;

        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'     => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity'       => $context->getEntity(),
            'cancelForm'   => $cancelForm
        ]));
    }

    public function sync(
        AdminUrlGenerator      $adminUrlGenerator,
        AdminContext           $adminContext,
        InvoiceSynchronizer    $invoiceSynchronizer,
        Request                $request,
        EntityManagerInterface $em,
    ): Response {

        try {
            $invoice = $adminContext->getEntity()->getInstance();
            $invoiceSynchronizer->syncInvoiceDetails($invoice);
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', 'The invoice has been synced successfully.');
        } catch (Exception $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirect($adminUrlGenerator
            ->setController(InvoiceCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoice->getId())
            ->generateUrl()
        );
    }

    public function fetch(
        AdminUrlGenerator      $adminUrlGenerator,
        EntityManagerInterface $em,
        InvoiceSynchronizer    $invoiceSynchronizer,
        Request                $request,
        TenantContext          $tenantContext,
    ): Response {
        try {
            $report  = $invoiceSynchronizer->fetchActiveSeries($tenantContext->getTenant());
            $message = '';
            foreach ($report as $key => $countProcessed) {
                $message .= sprintf('%s: %d<br/>', $key, $countProcessed);
            }
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', $message);
        } catch (EfClientException $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirect($adminUrlGenerator
            ->setController(InvoiceCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        );
    }

    public function cancel(
        AdminContext           $context,
        AdminUrlGenerator      $adminUrlGenerator,
        EFClient               $efClient,
        Request                $request,
        EntityManagerInterface $em
    ): RedirectResponse {

        /** @var Invoice $invoice */
        $invoice = $context->getEntity()->getInstance();

        try {
            $form = $this->createInvoiceCancelFrom($invoice);
            $form->handleRequest($request);
            if ($form->isSubmitted() and $form->isValid()) {
                $response = $efClient->cancelInvoice(
                    $invoice,
                    $invoice->getSubstitutedByInvoice(),
                    $invoice->getCancellationReason()
                );

                if (null === $cancellationDate = ($response['infoCancelacion']['fechaCancelacion'] ?? null)) {
                    throw new Exception('The invoice could not be cancelled. No cancellation date was returned.');
                }
                $cancellationDate = DateTime::createFromFormat('Y-m-d\TH:i:s', $cancellationDate);
                $invoice
                    ->setStatus(InvoiceStatus::CANCELADO)
                    ->setCancellationDate($cancellationDate)
                    ->setCancellationData($response);

                $em->flush();
                $request->getSession()->getFlashBag()->add('success', 'The invoice bas been cancelled.');
            } else {
                $request->getSession()->getFlashBag()->add('error', 'The invoice could not be cancelled.');
            }
        } catch (EfClientException $e) {
            $request->getSession()->getFlashBag()->add('error', 'EnlaceFiscal rejected the cancellation.');
        } catch (Exception $e) {
            $request->getSession()->getFlashBag()->add('error',
                'The invoice could not be cancelled: ' . $e->getMessage());
        }

        return $this->redirect($adminUrlGenerator
            ->setController(InvoiceCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($invoice->getId())
            ->generateUrl()
        );
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        $downloadInvoice = Action::new('downloadInvoice', '', 'fa fa-file-pdf')
            ->linkToRoute('admin_invoice_download', function (Invoice $invoice) {
                return [
                    'invoice' => $invoice->getId(),
                ];
            });
        $syncInvoice     = Action::new('sync', 'sync from EF', 'fa fa-refresh')
            ->linkToCrudAction('sync');
        $fetchLatest     = Action::new('fetch', 'fetch latest from EF', 'fa fa-download')
            ->linkToCrudAction('fetch')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, $fetchLatest)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('');
            })
            ->add(Crud::PAGE_DETAIL, $syncInvoice)
            ->add(Crud::PAGE_DETAIL, $downloadInvoice)
            ->add(Crud::PAGE_INDEX, $downloadInvoice)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('amount'))
            ->add(EntityFilter::new('customer'))
            ->add(EntityFilter::new('property'))
            ->add(TextFilter::new('description'))
            ->add(TextFilter::new('month'))
            ->add(TextFilter::new('number'))
            ->add(TextFilter::new('series'))
            ->add(ChoiceFilter::new('status')->setChoices(InvoiceStatus::getOptions()))
            ->add(TextFilter::new('year'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('invoiceUid')->setTemplatePath('admin/fields/invoiceUid.html.twig')->hideOnForm(),
            AssociationField::new('substitutesInvoice', 'substitutes'),
            TextField::new('invoicePeriod')->hideOnForm(),
            TextField::new('series')->hideOnIndex(),
            IntegerField::new('year')->hideOnIndex(),
            IntegerField::new('month')->hideOnIndex(),
            TextField::new('recipientRFC', 'RFC')->hideOnIndex(),
            TextField::new('checksum')->hideOnIndex(),
            AssociationField::new('property'),
            AssociationField::new('customer'),
            AssociationField::new('document'),
            ArrayField::new('data')->hideOnIndex()
                ->setTemplatePath('admin/fields/array.html.twig'),

            TextField::new('description')->hideOnForm(),
            UrlField::new('urlPdf')->onlyOnDetail(),
            UrlField::new('urlXml')->onlyOnDetail(),
            CentAmountField::new('amount', 'Amount')->hideOnForm(),
            CurrencyField::new('currency')->hideOnIndex(),
            DateTimeField::new('issueDate'),
            DateTimeField::new('paymentDate'),
            TextField::new('status')
                ->setTemplatePath('admin/fields/invoiceStatus.html.twig'),
            ArrayField::new('data', 'Details')->hideOnIndex()->hideOnDetail(),
            BooleanField::new('liveMode')->hideOnForm()
                ->setTemplatePath('admin/fields/liveModeStatus.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['series', 'number', 'customer.name', 'property.slug'])
            ->overrideTemplate('crud/detail', 'admin/Invoice/details.html.twig')
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular(function ($entity) {
                return $entity instanceof Receipt ? 'Receipt' : 'Invoice';
            })
            ->setEntityLabelInPlural('Invoices')
            ->setDefaultSort(['issueDate' => 'DESC']);
    }

    private function createInvoiceCancelFrom(Invoice $invoice): FormInterface
    {
        return $this->createForm(InvoiceCancelType::class, $invoice, [
            'action' => $this->adminUrlGenerator
                ->setController(InvoiceCrudController::class)
                ->setAction('cancel')
                ->setEntityId($invoice->getId())
                ->generateUrl()
        ]);
    }
}
