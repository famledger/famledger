<?php

namespace App\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class LiveModeDependent
{
    public function __construct(
        public string $livemodeFieldName
    ) {
    }
}
