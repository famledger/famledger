<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use App\Entity\Tenant;
use App\Repository\TenantRepository;

class TenantContext
{
    const DEFAULT_TENANT_ID = 2;
    const SESSION_KEY       = 'tenant_id';

    private ?Tenant $tenant = null;

    public function __construct(
        private readonly RequestStack     $requestStack,
        private readonly TenantRepository $tenantRepo
    ) {
    }

    public function setTenant(?Tenant $tenant = null): void
    {
        $this->tenant = $tenant ?? $this->fetchTenant(self::DEFAULT_TENANT_ID);
        $this->sessionSet($tenant);
    }

    public function getTenant(): ?Tenant
    {
        if (null === $this->tenant) {
            $this->tenant = $this->fetchTenant($this->sessionGet() ?? self::DEFAULT_TENANT_ID);
        }

        return $this->tenant;
    }

    private function sessionGet(): ?int
    {
        return $this->getSession()?->get(self::SESSION_KEY);
    }

    private function sessionSet(?Tenant $tenant): void
    {
        $this->getSession()?->set(self::SESSION_KEY, $tenant?->getId());
    }

    private function fetchTenant($tenantId): ?Tenant
    {
        return $this->tenantRepo->find($tenantId);
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getCurrentRequest()?->getSession();
        } catch (SessionNotFoundException $e) {
            return null;
        }
    }
}
