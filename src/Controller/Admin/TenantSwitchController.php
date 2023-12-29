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

        $referer = $request->headers->get('referer');
        if (!empty($referer)) {
            $queryString = parse_url($referer, PHP_URL_QUERY);
            parse_str($queryString, $queryParams);
            // cannot redirect to an entity details page
            if (!empty($queryParams['entityId'] ?? '')) {
                return new RedirectResponse('/admin');
            }
        }

        return new RedirectResponse($referer);
    }
}