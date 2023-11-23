<?php

namespace App\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class LiveModeFilterable
{
    public function __construct(
        public string $livemodeFieldName
    ) {
    }
}
