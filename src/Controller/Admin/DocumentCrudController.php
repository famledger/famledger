<?php

namespace App\Controller\Admin;

use App\Entity\Statement;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

use App\Admin\Field\CentAmountField;
use App\Admin\Field\DocumentPathField;
use App\Constant\DocumentType;
use App\Entity\Document;
use App\Event\DocumentRebuildEvent;
use App\Service\DocumentDetector\DocumentLoader;
use App\Service\DocumentFactory;
use App\Service\DocumentService;
use App\Service\QueryHelper;

class DocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly QueryHelper $queryHelper,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    use EntityHistoryButtonTrait;

    #[Route('/rebuild/{document}', name: 'admin_document_rebuild', methods: ['GET'])]
    public function rebuild(
        Document                 $document,
        EventDispatcherInterface $dispatcher,
        DocumentService          $documentService,
        DocumentLoader           $documentLoader,
        EntityManagerInterface   $em,
        Request                  $request,
        AdminUrlGenerator        $adminUrlGenerator
    ): Response {

        $filepath = $documentService->getAccountingFilepath($document);
        if (file_exists($filepath)) {
            try {
                $documentSpecs = $documentLoader->load(
                    $filepath,
                    pathinfo($filepath, PATHINFO_EXTENSION),
                    pathinfo($filepath, PATHINFO_BASENAME)
                );

                DocumentFactory::rebuildFromDocumentSpecs($document, $documentSpecs);
                $dispatcher->dispatch(new DocumentRebuildEvent($document));
                $em->flush();

                $request->getSession()->getFlashBag()->add('success', 'Document rebuilt successfully');
            } catch (Throwable $e) {
                $request->getSession()->getFlashBag()->add('error', $e->getMessage());
            }
        }

        return $this->redirect($adminUrlGenerator
            ->setController(DocumentCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($document->getId())
            ->generateUrl()
        );
    }

    #[Route('/relate/{document}/{statement}', name: 'admin_document_relate', methods: ['GET'])]
    public function relate(
        Document               $document,
        Statement              $statement,
        EntityManagerInterface $em,
        Request                $request,
        AdminUrlGenerator      $adminUrlGenerator
    ): Response {

        $error = null;
        if ($document->getIsRelated()) {
            $error = 'Document is already related to the statement';
        }

        if (null === $error) {
            $document
                ->setIsRelated(true)
                ->setStatement($statement);
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', 'Document related successfully');
        } else {
            $request->getSession()->getFlashBag()->add('error', $error);
        }

        return $this->redirect($adminUrlGenerator
            ->setController(StatementCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($statement->getId())
            ->generateUrl()
        );
    }

    #[Route('/unrelate/{document}', name: 'admin_document_unrelate', methods: ['GET'])]
    public function unrelate(
        Document               $document,
        Request                $request,
        EntityManagerInterface $em,
        AdminUrlGenerator      $adminUrlGenerator
    ): Response {

        $statement = $document->getStatement();
        try {
            if (false === $document->getIsRelated() or null === $statement) {
                throw new Exception('Document is not related to the statement');
            }
            if (null === $document->getFinancialMonth()) {
                throw new Exception('Document has no financial month');
            }
            $document->setIsRelated(false);
            $document->setStatement(null);
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', 'Document unrelated successfully');
        } catch (Exception $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirect($adminUrlGenerator
            ->setController(StatementCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($statement->getId())
            ->generateUrl()
        );
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

        $this->container->get(EntityFactory::class)
            ->processFields($context->getEntity(), FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));

        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));

        $this->container->get(EntityFactory::class)
            ->processActions($context->getEntity(), $context->getCrud()->getActionsConfig());


        return $this->configureResponseParameters(KeyValueStore::new([
            'pageName'     => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity'       => $context->getEntity(),
        ]));
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->addEntityHistoryAction($actions);

        $downloadDocument = Action::new('downloadDocument', '', 'fa fa-file-pdf text-success')
            ->linkToRoute('admin_document_download', function (Document $document) {
                return [
                    'document' => $document->getId()
                ];
            });

        $rebuildDocument = Action::new('rebuildDocument', '', 'fa fa-refresh text-warning')
            ->linkToRoute('admin_document_rebuild', function (Document $document) {
                return [
                    'document' => $document->getId()
                ];
            });

        $financialMonth = Action::new('financialMonth', 'Financial Month', 'fa fa-calendar')
            ->linkToRoute('admin', function (Document $entity) {
                return [
                    'crudAction'         => Crud::PAGE_DETAIL,
                    'crudControllerFqcn' => FinancialMonthCrudController::class,
                    'entityId'           => $entity->getFinancialMonth()->getId(),
                ];
            })
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_DETAIL, $rebuildDocument)
            ->add(Crud::PAGE_DETAIL, $financialMonth)
            ->add(Crud::PAGE_DETAIL, $downloadDocument)
            ->add(Crud::PAGE_INDEX, $downloadDocument)
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
            //->remove(Crud::PAGE_INDEX, Action::NEW)
            ->reorder(Crud::PAGE_INDEX, ['downloadDocument', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $filters
            ->add(NumericFilter::new('amount'))
            ->add(TextFilter::new('filename'))
            ->add(ChoiceFilter::new('type')
                ->setChoices($this->queryHelper->getPropertyOptions(Document::class, 'type')));

        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            ChoiceField::new('typeString')
                ->setChoices(DocumentType::getOptions())
                ->renderAsNativeWidget()
                ->allowMultipleChoices(false),
            IntegerField::new('year'),
            IntegerField::new('month')->setTemplatePath('admin/fields/monthAsString.html.twig'),
            AssociationField::new('financialMonth'),
            AssociationField::new('transaction')->hideOnForm(),
            AssociationField::new('invoice')->hideOnForm(),
            CentAmountField::new('amount'),
            TextField::new('checksum')->onlyOnDetail(),
            IntegerField::new('sequenceNo'),
            TextField::new('filename'),
            TextField::new('displayFilename')->hideOnForm(),
            TextField::new('comment')->hideOnIndex(),
            DocumentPathField::new('id')->onlyOnDetail(),
            BooleanField::new('isConsolidated')->hideOnForm(),
            DateField::new('created')->hideOnForm()->hideOnIndex(),
            ArrayField::new('specs')->hideOnIndex()->hideOnForm()
                ->setTemplatePath('admin/fields/array.html.twig'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['created' => 'DESC'])
            ->setSearchFields(['type', 'filename'])
            ->overrideTemplate('crud/detail', 'admin/Document/details.html.twig')
            ->showEntityActionsInlined();
    }
}
