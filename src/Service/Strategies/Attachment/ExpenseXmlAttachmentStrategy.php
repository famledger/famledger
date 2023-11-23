<?php

namespace App\Service\Strategies\Attachment;

use Angle\CFDI\CFDIInterface;

use App\Exception\DocumentMatchException;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\BaseCfdiStrategy;
use App\Service\Strategies\StrategyHelper;

class ExpenseXmlAttachmentStrategy extends BaseCfdiStrategy
{
    protected function specificMatchLogic(?CFDIInterface $cfdi): bool
    {
        // if the CFDi XML file has been issued to us, we can assume it's an expense invoice from somebody we paid
        return StrategyHelper::isTenantRfc($cfdi->getRecipientRfc());
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        if (null === $cfdi = $this->cfdi) {
            return null;
        }

        $year = $month = $accountNumber = $displayFilename = null;

        $cfdiData = StrategyHelper::getCfdiData($cfdi);
        switch($cfdiData['issuerRfc']) {

            case 'IAS981207459':
                $displayFilename = $cfdiData['recipientName'];
                break;

            case 'FEI100224KS6':
                $displayFilename = 'Gasto Facturacion Electronica';
                break;

            case 'PCA0312011M3':
            case 'PPC2101205P1':
                try {
                    $monthPattern = implode('|', StrategyHelper::MONTHS);
                    [$month, $year] = StrategyHelper::extractMonthAndYearFromDescription(
                        $cfdiData['description'],
                        [['/[A-Z]+ (' . $monthPattern . ')( de) (\d{4})/i', 1, 3]],
                    );

                    $displayFilename = sprintf('Gasto MNTO OFIC 216 %d-%02d.pdf', $year, $month);

                } catch (DocumentMatchException) {
                    $displayFilename = 'Gasto MNTO OFIC 216 ' . $cfdiData['description'];
                }
                break;

            case 'ACC010621N93':
                try {
                    $monthPattern = implode('|', StrategyHelper::MONTHS);
                    [$month, $year] = StrategyHelper::extractMonthAndYearFromDescription(
                        $cfdiData['description'],
                        [['/CUOTA MANTENIMIENTO (' . $monthPattern . ')\s+(\d{4})/i', 1, 2]],
                    );

                    $displayFilename = sprintf('Gasto MNTO OFIC 216 %d-%02d.pdf', $year, $month);

                } catch (DocumentMatchException) {
                    $displayFilename = 'Gasto MNTO OFIC 216 ' . $cfdiData['description'];
                }
                break;

            case 'CIB1401207M1':
            case 'CAP0201093L7':
                try {
                    $monthPattern = implode('|', StrategyHelper::MONTHS);
                    [$month, $year] = StrategyHelper::extractMonthAndYearFromDescription(
                        $cfdiData['description'],
                        [['/CUOTA MANT[A-Z]+ (' . $monthPattern . ') (\d{4})/', 1, 2]]
                    );
                    $displayFilename = sprintf('Gasto Contabilidad %d-%02d.pdf', $year, $month);
                } catch (DocumentMatchException) {
                    $displayFilename = 'Gasto Contabilidad ' . $cfdiData['description'];
                }
                break;

            default:
                break;
        }
        if ('MOPM670510J8A' === $cfdiData['recipientRfc']) {
            $accountNumber = '1447391412';
        }

        return (new AttachmentSpecs())
            ->setAmount($cfdiData['amount'])
            ->setDescription($cfdiData['description'])
            ->setIssueDate($cfdiData['issueDate'])
            ->setDisplayFilename($displayFilename)
            ->setAccountNumber($accountNumber)
            ->setYear((int)$year)
            ->setMonth((int)$month);
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        return basename($filePath);
    }
}