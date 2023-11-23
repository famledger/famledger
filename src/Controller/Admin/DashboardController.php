<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
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
use App\Entity\FinancialMonth;
use App\Entity\Invoice;
use App\Entity\InvoiceSchedule;
use App\Entity\InvoiceTask;
use App\Entity\Property;
use App\Entity\Series;
use App\Entity\Statement;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\LiveModeContext;
use App\Service\TenantContext;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiveModeContext        $liveModeContext,
        private readonly TenantContext          $tenantContext,
        private readonly RequestStack           $requestStack
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        //return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(TenantCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        $tenant     = $this->tenantContext->getTenant();
        $tenantName = $tenant->getName();
        $icon       = $tenant->getIcon() ?? 'building';

        return Dashboard::new()
            ->renderContentMaximized()
            ->setTitle(<<<HTML
<div class="app-name">FamLedger</div>
<span class="tenant-caption">$tenantName <i class="fa fa-$icon"></i></span> 
HTML
            );
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
        yield MenuItem::section('Invoicing');
        yield MenuItem::linkToCrud('Invoice Schedules', 'fa fa-calendar', InvoiceSchedule::class);
        yield MenuItem::linkToCrud('Invoice Tasks', 'fas fa-tasks', InvoiceTask::class);
        yield MenuItem::linkToRoute('Invoice History', 'fas fa-history', 'admin_invoice_history');
        yield MenuItem::linkToCrud('Invoices', 'fas fa-file-invoice', Invoice::class);
        yield MenuItem::linkToRoute($liveModeLabel, $liveModeToggleIcon, 'liveModeSwitch',
            ['redirectUrl' => $redirectUrl])->setCssClass($liveModeCss);
        yield MenuItem::section('Accounting');
        yield MenuItem::linkToRoute('Inbox', 'fas fa-inbox', 'admin_inbox');
        yield MenuItem::linkToCrud('Statements', 'fas fa-balance-scale', Statement::class);
        yield MenuItem::linkToRoute('Payment History', 'fas fa-history', 'admin_payment_history');
        yield MenuItem::linkToCrud('Bank Accounts', 'fas fa-bank', Account::class);
        yield MenuItem::linkToCrud('Financial Months', 'fas fa-calendar', FinancialMonth::class);
        yield MenuItem::linkToCrud('Documents', 'fas fa-file', Document::class);
        yield MenuItem::section('Admin');
        yield MenuItem::linkToCrud('Properties', 'fas fa-building', Property::class);
        yield MenuItem::linkToCrud('Customers', 'fas fa-user', Customer::class);
        yield MenuItem::linkToCrud('Addresses', 'fas fa-address-card', Address::class);
        yield MenuItem::linkToCrud('Family', 'fas fa-people-arrows', Address::class);
        yield MenuItem::section('Miscellaneous');
        yield MenuItem::linkToCrud('Vehicles', 'fas fa-car', Vehicle::class);
        yield MenuItem::linkToCrud('E-Docs', 'fas fa-file', EDoc::class);
        yield MenuItem::section('Setup');
        yield MenuItem::linkToCrud('Tenants', 'fas fa-landmark-flag', Tenant::class);
        yield MenuItem::linkToCrud('Series', 'fas fa-heading', Series::class);
        yield MenuItem::linkToCrud('Users', 'fas fa-user', User::class);
        yield MenuItem::section('System');
        yield MenuItem::linkToRoute('Document Detector', 'fas fa-search', 'admin_document_detect');
        yield MenuItem::linkToRoute('Statement inconsistencies', 'fas fa-block', 'admin_statement_inconsistencies');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        $menu          = parent::configureUserMenu($user)
//            // use the given $user object to get the user name
            ->setName($user->getUserIdentifier())
//            // use this method if you don't want to display the name of the user
            ->displayUserName(true)
//
//            // you can return an URL with the avatar image
//            ->setAvatarUrl('https://avatars1.githubusercontent.com/u/1295390?s=60&v=4')
//            //->setAvatarUrl($user->getProfileImageUrl())
//            // use this method if you don't want to display the user image
//            ->displayUserAvatar(false)
            // you can also pass an email address to use gravatar's service
            //->setGravatarEmail($user->getMainEmailAddress())

            // you can use any type of menu item, except submenus
//            ->addMenuItems([
//                MenuItem::section('Switch country'),
//                MenuItem::linkToRoute('Germany', 'fa fa-flag', 'list', ['country' => 'DE']),
//                MenuItem::linkToRoute('USA', 'fa fa-flag', 'list', ['country' => 'US']),
//                MenuItem::section('----------'),
//            ]);
        ;
        $currentTenant = $this->tenantContext->getTenant();
        $tenants       = $this->em->getRepository(Tenant::class)->findAll();
        $menuItems     = [MenuItem::section('Tenants')];
        foreach ($tenants as $tenant) {
            $marker      = $tenant === $currentTenant ? 'fa fa-check text-success' : 'fa fa-circle text-muted';
            $redirectUrl = urlencode($this->requestStack->getCurrentRequest()->getUri());

            $menuItems[] = MenuItem::linkToRoute(
                $tenant->getName(),
                $marker,
                'tenantSwitch',
                // set the redirectUrl to the current URL
                ['tenant' => $tenant->getId(), 'redirectUrl' => $redirectUrl]
            )->setCssClass('text-muted');
        }
        $menu->addMenuItems($menuItems);

//        $countries = $user->getCountries();
//        if (1 < count($countries)) {
//            $menuItems = [MenuItem::section('Switch country')];
//            foreach ($countries as $country) {
//                if ($country === $this->countryContext->getCurrentCountry()) {
//                    continue;
//                }
//                $menuItems[] = MenuItem::linkToRoute($country, 'fa fa-flag', 'countrySwitch', ['country' => $country]);
//            }
//            $menu->addMenuItems($menuItems);
//        }

        return $menu;
    }
}
