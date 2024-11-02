<?php

namespace App\Controller\Admin;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Entity\Account;
use App\Entity\Address;
use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\EDoc;
use App\Entity\FamilyMember;
use App\Entity\Invoice;
use App\Entity\InvoiceSchedule;
use App\Entity\InvoiceTask;
use App\Entity\Property;
use App\Entity\Receipt;
use App\Entity\ReceiptTask;
use App\Entity\Series;
use App\Entity\Statement;
use App\Entity\TaxNotice;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\AccountRepository;
use App\Repository\InvoiceRepository;
use App\Repository\InvoiceTaskRepository;
use App\Service\LiveModeContext;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly InvoiceRepository      $invoiceRepository,
        private readonly InvoiceTaskRepository  $invoiceTaskRepository,
        private readonly LiveModeContext        $liveModeContext,
        private readonly RequestStack           $requestStack,
        private readonly AccountRepository      $accountRepository,
        private readonly AdminUrlGenerator      $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'invoices'     => $this->invoiceRepository->findLatest(),
            'invoiceTasks' => $this->invoiceTaskRepository->findPending(),
            'accounts'     => $this->accountRepository->findActive(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->renderContentMaximized();
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->displayUserName()
//            ->setAvatarUrl('https://avatars1.githubusercontent.com/u/1295390?s=60&v=4')
//            //->setAvatarUrl($user->getProfileImageUrl())
//            // use this method if you don't want to display the user image
//            ->displayUserAvatar(false)
            // you can also pass an email address to use gravatar's service
            //->setGravatarEmail($user->getMainEmailAddress())
            ;
    }

    public function configureAssets(): Assets
    {
        $assets = Assets::new()
            ->addCssFile('css/admin.css');

        if (false === $this->liveModeContext->getLiveMode()) {
            $assets->addCssFile('css/debugMode.css');
        }

        return $assets;
    }

    public function configureMenuItems(): iterable
    {
        $liveMode           = $this->liveModeContext->getLiveMode();
        $liveModeToggleIcon = $liveMode ? 'fa fa-toggle-off' : 'fa fa-toggle-on';
        $liveModeCss        = $liveMode ? '' : 'highlight';
        $liveModeLabel      = $liveMode ? 'work in test mode' : 'working in test mode';
        $redirectUrl        = urlencode($this->requestStack->getCurrentRequest()->getUri());

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Inbox', 'fas fa-inbox', 'admin_inbox');
        yield MenuItem::linkToUrl('Outbox', 'fas fa-sign-out',
            'folderopener:///Volumes/KC3000-2TB/DataStorage/FamLedger/outbox');
        yield MenuItem::section('Invoicing');
        yield MenuItem::linkToCrud('Invoice Schedules', 'fa fa-calendar', InvoiceSchedule::class);
        yield MenuItem::linkToCrud('Invoice Tasks', 'fas fa-tasks', InvoiceTask::class);
        yield MenuItem::linkToCrud('Receipt Tasks', 'fas fa-tasks', ReceiptTask::class);
        yield MenuItem::section('Accounting');
        yield MenuItem::linkToRoute('Invoice History', 'fas fa-history', 'admin_invoice_history',
            ['year' => date('Y')]);
        yield MenuItem::linkToRoute('Payment History', 'fas fa-history', 'admin_payment_history',
            ['year' => date('Y')]);
        yield MenuItem::linkToCrud('Tax Payments History', 'fas fa-cash-register', TaxNotice::class);
        yield MenuItem::linkToCrud('Statements', 'fas fa-balance-scale', Statement::class);
        yield MenuItem::linkToRoute('Yearly Expenses', 'fas fa-credit-card', 'admin_expense', ['year' => date('Y')]);
        yield MenuItem::linkToRoute('Yearly Reports', 'fas fa-chart-bar', 'admin_yearlyReport', ['year' => date('Y')]);
//        yield MenuItem::linkToCrud('Financial Months', 'fas fa-calendar', FinancialMonth::class);
        yield MenuItem::section('Lookup');
        yield MenuItem::linkToCrud('Documents', 'fas fa-file', Document::class);
        yield MenuItem::linkToCrud('Invoices', 'fas fa-file-invoice', Invoice::class);
        yield MenuItem::linkToCrud('Receipts', 'fas fa-file-invoice', Receipt::class);
        yield MenuItem::linkToCrud('Transactions', 'fas fa-right-left', Transaction::class);
        yield MenuItem::section('Admin');
        yield MenuItem::linkToCrud('Bank Accounts', 'fas fa-bank', Account::class);
        yield MenuItem::linkToCrud('Properties', 'fas fa-building', Property::class);
        yield MenuItem::linkToCrud('Customers', 'fas fa-user', Customer::class);
        yield MenuItem::linkToCrud('Addresses', 'fas fa-address-card', Address::class);
        $members = $this->em->getRepository(FamilyMember::class)->findAll();
        yield MenuItem::section('Family', 'fas fa-people-arrows');
        if (1 < count($members)) {
            foreach ($members as $member) {
                yield MenuItem::linkToUrl(
                    $member->getFirstname(),
                    'demo-icon icon-' . strtolower($member->getSlug()),
                    $this->adminUrlGenerator
                        ->setController(FamilyMemberCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($member->getId())
                        ->generateUrl()
                );
            }
        }
        yield MenuItem::section('Miscellaneous');
        yield MenuItem::linkToRoute('Information', 'fas fa-info-circle', 'admin_info');
        yield MenuItem::linkToCrud('Vehicles', 'fas fa-car', Vehicle::class);
        yield MenuItem::linkToCrud('E-Docs', 'fas fa-file', EDoc::class);
        yield MenuItem::section('Setup');
        yield MenuItem::linkToCrud('Tenants', 'fas fa-landmark-flag', Tenant::class);
        yield MenuItem::linkToCrud('Series', 'fas fa-heading', Series::class);
        yield MenuItem::linkToCrud('Users', 'fas fa-user', User::class);
        yield MenuItem::section('System');
        yield MenuItem::linkToRoute('Document Detector', 'fas fa-search', 'admin_document_detect');
        yield MenuItem::linkToRoute('Statement inconsistencies', 'fas fa-block', 'admin_statement_inconsistencies');
        yield MenuItem::linkToRoute($liveModeLabel, $liveModeToggleIcon, 'liveModeSwitch',
            ['redirectUrl' => $redirectUrl])->setCssClass($liveModeCss);
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->disable(Action::BATCH_DELETE);
    }
}
