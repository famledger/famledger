<?php

namespace App\Service\Strategies\Attachment;

use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class IASAttachmentStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'INTERNATIONAL AMERICAN SCHOOL')
               and str_contains($content, 'IAS981207459');
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $reducedContent = StrategyHelper::reduceSpaces($content);

        // Total:     $3,400.01
        $value  = StrategyHelper::extractValue(
            '/Total:.*\$\s*([0-9,.]+)/i',
            $reducedContent,
            $filePath,
            'a pagar:'
        );
        $amount = StrategyHelper::convertToIntegerAmount($value);

        return (new AttachmentSpecs())
            ->setDisplayFilename('Colegiatura Alessa Miridis IAS.pdf')
            ->setAmount($amount)
            ->setAccountNumber('1447391412');
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}