<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Tenant;
use App\Service\TenantContext;

class TenantSwitchController extends AbstractController
{
    #[Route('/tenantSwitch', name: 'tenantSwitch')]
    public function index(
        Request                $request,
        TenantContext          $context,
        EntityManagerInterface $em
    ): RedirectResponse {
        $tenant = $em->getRepository(Tenant::class)->find($request->get('tenant'));
        $context->setTenant($tenant);
        $redirectUrl = urldecode($request->get('redirectUrl'));

        return new RedirectResponse($redirectUrl);
    }
}