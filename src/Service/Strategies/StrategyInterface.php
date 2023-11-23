<?php

namespace App\Service\Strategies;

use App\Exception\DocumentParseException;
use App\Service\DocumentSpecs\BaseDocumentSpecs;

interface StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool;

    /**
     * @throws DocumentParseException
     */
    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs;

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string;
}