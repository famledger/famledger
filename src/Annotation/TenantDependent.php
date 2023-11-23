<?php

namespace App\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class TenantDependent
{
    public function __construct(
        public string $tenantFieldName
    ) {
    }
}
