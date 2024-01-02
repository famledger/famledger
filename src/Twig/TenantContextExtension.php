<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Entity\Tenant;
use App\Repository\TenantRepository;
use App\Service\TenantContext;

class TenantContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly TenantContext    $tenantContext,
        private readonly TenantRepository $tenantRepo,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant', [$this, 'getTenant'], ['is_safe' => ['html']]),
            new TwigFunction('tenantOptions', [$this, 'getTenantOptions'], ['is_safe' => ['html']]),
        ];
    }

    public function getTenant(): Tenant
    {
        return $this->tenantContext->getTenant();
    }

    public function getTenantOptions(): array
    {
        $currentTenant = $this->tenantContext->getTenant();
        $tenantOptions = [];
        foreach ($this->tenantRepo->findAll() as $tenant) {
            if ($tenant->getId() !== $currentTenant->getId()) {
                $tenantOptions[] = $tenant;
            }
        }

        return $tenantOptions;
    }
}