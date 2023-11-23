<?php

namespace App\Service\Strategies\Attachment;

use Angle\CFDI\CFDIInterface;

use App\Exception\DocumentMatchException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\BaseCfdiStrategy;
use App\Service\Strategies\StrategyHelper;

class InvoiceAttachmentStrategy extends BaseCfdiStrategy
{

    protected function specificMatchLogic(?CFDIInterface $cfdi): bool
    {
        // if the CFDi XML file has been issued by us, we can assume it accompanies an invoice we issued
        return StrategyHelper::isTenantRfc($cfdi->getIssuerRfc());
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        if (null === $cfdi = $this->cfdi) {
            return null;
        }

        $year = $month = $account = $displayFilename = null;
        $cfdiData = StrategyHelper::getCfdiData($cfdi);
        switch($cfdiData['issuerRfc']) {

            case 'MIJO620503Q60':
                $displayFilename = StrategyHelper::formatFromCfdi(
                    'Factura #series#-#folio# - #recipientName#.xml',
                    $cfdiData
                );
                break;

            case 'MOPM670510J8A':
                try {
                    [$month, $year] = StrategyHelper::extractMonthAndYearFromDescription(
                        $cfdiData['description'],
                        [['/Periodo:\s+\d{1,2}\s+de\s+(\w+)\s+(\d{4})\s+al\s+(\d{1,2}\s+de\s+\w+\s+\d{4})/i', 1, 2]]
                    );
                    $displayFilename = StrategyHelper::formatFromCfdi(
                        'Factura #folio#-#series#-#year#-#month# #recipientName#.xml',
                        array_merge($cfdiData, ['month' => sprintf('%02d', $month), 'year' => (string)$year])
                    );
                } catch (DocumentMatchException) {
                    $displayFilename = 'Factura ' . $cfdiData['description'];
                }
                break;

            default:
                break;
        }

        return (new AttachmentSpecs())
            ->setAmount($cfdiData['amount'])
            ->setIssueDate($cfdiData['issueDate'])
            ->setDescription($cfdiData['description'])
            ->setDisplayFilename($displayFilename ?? null)
            ->setAccountNumber($account ?? null)
            ->setYear(is_string($year) ? (int)$year : $year)
            ->setMonth(is_string($month) ? (int)$month : $month)
            ->setInvoiceSeries($cfdiData['series'])
            ->setInvoiceNumber($cfdiData['folio']);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}