<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Entity\Tenant;
use App\Service\TenantContext;

class TenantContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant', [$this, 'getTenant'], ['is_safe' => ['html']]),
        ];
    }

    public function getTenant(): Tenant
    {
        return $this->tenantContext->getTenant();
    }
}