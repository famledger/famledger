<?php

namespace App\Entity;

interface TenantAwareInterface
{
    public function setTenant(Tenant $tenant): static;

    public function getTenant(): ?Tenant;
}