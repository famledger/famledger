<?php

namespace App\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\Document;
use App\Entity\FinancialMonth;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\DocumentService;

class DocumentPathExtension extends AbstractExtension
{
    public function __construct(
        private readonly DocumentService         $documentService,
        private readonly AccountingFolderManager $accountingFolderManager,
        private readonly EntityManagerInterface  $em
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('document_path', [$this, 'getDocumentPath'], ['is_safe' => ['html']]),
            new TwigFilter('financial_month_path', [$this, 'getFinancialMonthPath'], ['is_safe' => ['html']])
        ];
    }

    public function getDocumentPath(?int $documentId = null, ?bool $absolute = false): string
    {
        try {
            if (null === $documentId) {
                throw new Exception('Document not found');
            }
            if (null !== $document = $this->em->getRepository(Document::class)->find($documentId)) {
                return $this->documentService->getAccountingFilepath($document, $absolute, true);
            }
            throw new Exception('Document not found: ' . $documentId);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getFinancialMonthPath(?FinancialMonth $financialMonth = null, ?bool $isAttachment = false): string
    {
        if (null == $financialMonth) {
            return '';
        }

        try {
            return $this->accountingFolderManager->getAccountingFolderPath($financialMonth, $isAttachment);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}