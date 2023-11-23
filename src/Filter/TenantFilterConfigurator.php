<?php

namespace App\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

use App\Service\TenantContext;

#[AsEventListener(KernelEvents::REQUEST, priority: 10)]
class TenantFilterConfigurator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext          $tenantContext
    ) {
    }

    public function onKernelRequest(): void
    {
        // the TenantFilter must be enabled for each request
        /** @var TenantFilter $filter */
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant', $this->tenantContext->getTenant()?->getId());
    }
}