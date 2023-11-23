<?php

namespace App\Entity;

interface TenantAwareInterface
{
    public function setTenant(Tenant $tenant): self;

    public function getTenant(): ?Tenant;
}