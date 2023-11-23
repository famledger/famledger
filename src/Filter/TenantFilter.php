<?php

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Exception;

use App\Annotation\TenantFilterable;

class TenantFilter extends SQLFilter
{
    /**
     * @throws Exception
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        $tenant = null;

        // Attempt to retrieve the 'tenant' parameter
        try {
            $tenant = $this->getParameter('tenant');
        } catch (Exception) {
        }

        // Return early if no tenant has been specified or tenant is 'ALL'
        if (null === $tenant or "'ALL'" === $tenant) {
            return '';
        }

        // Check if the current entity is "tenant filterable"
        $tenantFilterableAttribute = $targetEntity->getReflectionClass()->getAttributes(TenantFilterable::class);

        if (empty($tenantFilterableAttribute)) {
            return '';
        }

        /** @var TenantFilterable $tenantFilterable */
        $tenantFilterable = $tenantFilterableAttribute[0]->newInstance();
        $fieldName        = $tenantFilterable->tenantFieldName;

        // Ensure a field name is provided for tenant filterable entities
        if (empty($fieldName)) {
            throw new Exception('TenantFilter requires tenantFieldName to be defined.');
        }

        return sprintf("%s.%s = %s", $targetTableAlias, $fieldName, $tenant);
    }
}