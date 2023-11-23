<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Attachment\ExpenseXmlAttachmentStrategy;
use App\Service\Strategies\Attachment\InvoiceAttachmentStrategy;

class XmlAttachmentDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new InvoiceAttachmentStrategy(),
            new ExpenseXmlAttachmentStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'xml';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::ATTACHMENT;
    }
}
