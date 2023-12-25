<?php

namespace App\Service\Detectors;

use App\Constant\DocumentType;
use App\Service\Strategies\Attachment\AcuseDeclaracionStrategy;
use App\Service\Strategies\Attachment\BTMAttachmentStrategy;
use App\Service\Strategies\Attachment\CAPAttachmentStrategy;
use App\Service\Strategies\Attachment\DeclaracionProvisionalStrategy;
use App\Service\Strategies\Attachment\EnlaceFiscalAttachmentStrategy;
use App\Service\Strategies\Attachment\IASAttachmentStrategy;
use App\Service\Strategies\Attachment\PABAttachmentStrategy;
use App\Service\Strategies\Attachment\TulumExpenseAttachmentStrategy;

class TaxNoticeAttachmentDetector extends BaseDetector
{
    public function getStrategies(): array
    {
        return [
            new AcuseDeclaracionStrategy(),
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return $format === 'text';
    }

    public function supportsType(): DocumentType
    {
        return DocumentType::TAX_NOTICE;
    }
}
