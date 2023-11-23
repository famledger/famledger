<?php

namespace App\Service\DocumentDetector;

use App\Constant\DocumentType;
use App\Service\DocumentSpecs\BaseDocumentSpecs;

interface DocumentDetectorInterface
{
    public function detect(string $content, ?string $filePath = null, ?string $originalName = null): ?BaseDocumentSpecs;

    public function supportsFormat(string $format): bool;

    public function supportsType(): DocumentType;

}
