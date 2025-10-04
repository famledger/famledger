<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Entity\Contract;
use App\Entity\EDoc;
use App\Service\EDocService;

class ContractFileExtension extends AbstractExtension
{
    public function __construct(
        private readonly EDocService $eDocService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('contract_file', $this->getContractFile(...)),
            new TwigFunction('has_contract_file', $this->hasContractFile(...)),
            new TwigFunction('customer_contract_edocs', $this->getCustomerContractEDocs(...)),
            new TwigFunction('property_contract_edocs', $this->getPropertyContractEDocs(...)),
        ];
    }

    public function getContractFile(?Contract $contract): ?EDoc
    {
        if (!$contract) {
            return null;
        }
        return $this->eDocService->getContractFile($contract);
    }

    public function hasContractFile(?Contract $contract): bool
    {
        if (!$contract) {
            return false;
        }
        return $this->eDocService->hasContractFile($contract);
    }

    public function getCustomerContractEDocs($customer, ?string $type = null): array
    {
        return $this->eDocService->getCustomerContractEDocs($customer, $type);
    }

    public function getPropertyContractEDocs($property, ?string $type = null): array
    {
        return $this->eDocService->getPropertyContractEDocs($property, $type);
    }
}