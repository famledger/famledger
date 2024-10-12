<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use App\Admin\Field\CentAmountField;
use App\Constant\DocumentType;
use App\Entity\Transaction;

class TransactionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator      $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Transaction::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort(['bookingDate' => 'DESC'])
            ->setSearchFields(['amount', 'description']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('account')
            ->add('amount')
            ->add('description')
            ->add('type');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('account')->setFormTypeOption('query_builder', function ($repository) {
            return $repository->createQueryBuilder('a')->orderBy('a.caption', 'ASC');
        });
        yield ChoiceField::new('type')->setChoices([
            DocumentType::DONATION->value => DocumentType::DONATION->value,
            DocumentType::EXPENSE->value  => DocumentType::EXPENSE->value,
            DocumentType::INCOME->value   => DocumentType::INCOME->value,
            DocumentType::PAYMENT->value  => DocumentType::PAYMENT->value,
            DocumentType::TAX->value      => DocumentType::TAX->value,
        ]);
        yield CentAmountField::new('amount', 'Amount')->hideOnForm();
        yield DateField::new('bookingDate');
        yield TextareaField::new('description');
    }

    public function configureActions(Actions $actions): Actions
    {
        $statementDetailAction = Action::new('statementDetail', 'View Statement')
            ->setIcon('fa fa-balance-scale')
            ->linkToUrl(function (?Transaction $transaction) {
                $hash = (null == $transaction)
                    ? ''
                    : '#position_' . $transaction->getSequenceNo();

                return $this->adminUrlGenerator
                           ->setController(StatementCrudController::class)
                           ->setAction(Action::DETAIL)
                           ->setEntityId($transaction->getStatement()?->getId())
                           ->generateUrl() . $hash;
            })
            ->displayIf(function (?Transaction $transaction) {
                return null !== $transaction->getStatement()?->getId();
            });

        $exportAction = Action::new('export', 'Export')
            ->setIcon('fa fa-file-export')
            ->linkToCrudAction('exportData')
            ->createAsGlobalAction();

        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $statementDetailAction);
    }

    public function exportData(AdminContext $context): Response
    {
        // Get the QueryBuilder for the current entity (Transaction)
        $repository   = $this->em->getRepository(Transaction::class);
        $queryBuilder = $repository->createQueryBuilder('t');

        // Apply any filters (you can define your filter logic here)
        $filters = $context->getRequest()->query->all(); // Get the filters from the request
        if (isset($filters['account'])) {
            $queryBuilder->andWhere('t.account = :account')
                ->setParameter('account', $filters['account']);
        }
        if (isset($filters['description'])) {
            $queryBuilder->andWhere('t.description LIKE :description')
                ->setParameter('description', '%' . $filters['description'] . '%');
        }

        // Execute the query and get results
        $transactions = $queryBuilder->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Set the headers for the Excel file
        $sheet->setCellValue('A1', 'ID')
            ->setCellValue('B1', 'Account')
            ->setCellValue('C1', 'Amount')
            ->setCellValue('D1', 'Booking Date')
            ->setCellValue('E1', 'Description');

        // Loop through transactions and write them to the spreadsheet
        $row = 2; // Start from the second row
        foreach ($transactions as $transaction) {
            $sheet->setCellValue('A' . $row, $transaction->getId())
                ->setCellValue('B' . $row, $transaction->getAccount()?->getCaption())
                ->setCellValue('C' . $row, $transaction->getAmount())
                ->setCellValue('D' . $row, $transaction->getBookingDate()?->format('Y-m-d'))
                ->setCellValue('E' . $row, $transaction->getDescription());
            $row++;
        }

        $writer    = new Xlsx($spreadsheet);
        $temp_file = tempnam(sys_get_temp_dir(), 'transactions_export');

        $writer->save($temp_file);

        return $this->file($temp_file, 'transactions_export.xlsx', ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
