<?php

namespace App\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use App\Constant\ContractEDocType;
use App\Constant\PropertyEDocType;
use App\Entity\Contract;
use App\Entity\Customer;
use App\Entity\EDoc;
use App\Entity\FileOwnerInterface;
use App\Entity\Property;
use App\Service\DocumentSpecs\ReceiptSpecs;

class EDocService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext          $tenantContext,
        private readonly string                 $eDocsRootFolder,
    ) {
    }

    public function createAndPersistFromDocumentSpecs(ReceiptSpecs $documentSpecs): ?EDoc
    {
        $propertySlug = $documentSpecs->getPropertySlug();
        $property     = $this->em->getRepository(Property::class)->findOneBy(['slug' => $propertySlug]);
        if (null === $property) {
            return null;
        }

        $this->em->beginTransaction();
        try {
            $sourceFilepath = $documentSpecs->getFilePath();
            $eDoc           = (new EDoc())
                ->setOwnerId($property->getId())
                ->setOwnerType('Property')
                ->setOwnerKey($propertySlug)
                ->setType(PropertyEDocType::ELECTRICITY)
                ->setFilename($documentSpecs->getSuggestedFilename())
                ->setFormat('pdf')
                ->setTenant($this->tenantContext->getTenant())
                ->setChecksum(hash('sha256', file_get_contents($sourceFilepath)))
                ->setCreated(new DateTime());

            $targetFilePath = $this->getEDocFilepath($eDoc);
            if (file_exists($targetFilePath)) {
                throw new Exception("File with the same name already exists at $targetFilePath");
            }

            $this->em->persist($eDoc);
            $this->em->flush();

            $targetFolder = $this->getEDocFolder($eDoc);
            if (!is_dir($targetFolder)) {
                mkdir($targetFolder, 0777, true);
            }

            rename($sourceFilepath, $targetFilePath);

            $this->em->commit();

            return $eDoc;
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function createAndPersistUploadedEDoc(
        FileOwnerInterface $owner,
        UploadedFile       $file,
        string             $type
    ): EDoc {
        $this->em->beginTransaction();

        try {
            $ownerType = $this->getOwnerType($owner);

            $eDoc = (new EDoc())
                ->setOwnerId($owner->getId())
                ->setOwnerType($ownerType)
                ->setOwnerKey($owner->getOwnerKey())
                ->setType($type)
                ->setFilename($file->getClientOriginalName())
                ->setFormat($file->getClientOriginalExtension())
                ->setTenant($this->tenantContext->getTenant())
                ->setChecksum(hash('sha256', file_get_contents($file->getPathname())))
                ->setCreated(new DateTime());

            $filePath = $this->getEDocFilepath($eDoc);
            if (file_exists($filePath)) {
                throw new Exception("File with the same name already exists at $filePath");
            }

            $this->em->persist($eDoc);
            $this->em->flush();

            $this->saveFileToDisk($eDoc, $file);

            $this->em->commit();

            return $eDoc;
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function deleteEDoc(EDoc $eDoc): void
    {
        $this->em->beginTransaction();

        try {
            // Delete file from disk
            $filePath = $this->getEDocFilepath($eDoc);
            if (file_exists($filePath)) {
                unlink($filePath);
            } else {
                throw new Exception("File does not exist at $filePath");
            }

            // Remove from database
            $this->em->remove($eDoc);
            $this->em->flush();

            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function getEDocsByType(FileOwnerInterface $owner, ?string $type = null): array
    {
        $eDocs = [];
        foreach ($this->getEDocs($owner, $type) as $eDoc) {
            /** @var EDoc $eDoc */
            $eDocs[$eDoc->getType()][] = [
                'id'        => $eDoc->getId(),
                'ownerType' => $eDoc->getOwnerType(),
                'type'      => $eDoc->getType(),
                'filename'  => $eDoc->getFilename(),
                'format'    => $eDoc->getFormat(),
                'createdAt' => $eDoc->getCreated()->format('Y-m-d H:i:s')
            ];
        }

        return $eDocs;
    }

    public function getEDocs(FileOwnerInterface $owner, ?string $type = null): array
    {
        $constraints = array_filter([
            'ownerId'   => $owner->getId(),
            'ownerType' => $this->getOwnerType($owner),
            'type'      => $type
        ]);

        return $this->em->getRepository(EDoc::class)->findBy($constraints,['filename' => 'DESC']);
    }

    public function getOwner(EDoc $eDoc): ?FileOwnerInterface
    {
        $class = "App\\Entity\\{$eDoc->getOwnerType()}";

        return $this->em->getRepository($class)->find($eDoc->getOwnerId());
    }

    public function getEDocFilepath(EDoc $eDoc): string
    {
        return sprintf(
            "%s/%s",
            $this->getEDocFolder($eDoc),
            $eDoc->getFilename()
        );
    }

    /**
     * @throws Exception
     */
    public function renameFileOnDisk(EDoc $entity, string $oldFilename, string $newFilename): void
    {
        $folder     = $this->getEDocFolder($entity);
        $sourcePath = "$folder/$oldFilename";
        $targetPath = "$folder/$newFilename";

        // Check if the source file exists
        if (!file_exists($sourcePath)) {
            throw new Exception("Source file '$sourcePath' does not exist.");
        }

        // Check if the target file already exists
        if (file_exists($targetPath)) {
            throw new Exception("Target file '$newFilename' already exists.");
        }

        // Attempt to rename the file
        if (!rename("$folder/$oldFilename", $targetPath)) {
            throw new Exception("Failed to rename the file to '$newFilename'.");
        }
    }

    private function saveFileToDisk(EDoc $eDoc, UploadedFile $file): void
    {
        $targetFolder = $this->getEDocFolder($eDoc);
        if (!is_dir($targetFolder)) {
            mkdir($targetFolder, 0777, true);
        }

        $file->move($targetFolder, $file->getClientOriginalName());
    }

    private function getEDocFolder(EDoc $eDoc): string
    {
        return sprintf(
            "%s/%s/%s/%s/%s",
            $this->eDocsRootFolder,
            $eDoc->getTenant()->getRfc(),
            $eDoc->getOwnerType(),
            $eDoc->getOwnerKey(),
            $eDoc->getType(),
        );
    }

    public function getContractFile(Contract $contract): ?EDoc
    {
        return $this->em->getRepository(EDoc::class)->findOneBy([
            'ownerId' => $contract->getId(),
            'ownerType' => 'Contract',
            'type' => ContractEDocType::CONTRACT_FILE
        ]);
    }

    public function hasContractFile(Contract $contract): bool
    {
        return $this->getContractFile($contract) !== null;
    }

    /**
     * Get contract eDocs related to a Customer
     */
    public function getCustomerContractEDocs(Customer $customer, ?string $type = null): array
    {
        $contractRepository = $this->em->getRepository(Contract::class);
        $contracts = $contractRepository->findBy(['customer' => $customer]);
        
        $eDocs = [];
        foreach ($contracts as $contract) {
            $contractEDocs = $this->getEDocs($contract, $type);
            $eDocs = array_merge($eDocs, $contractEDocs);
        }
        
        return $eDocs;
    }

    /**
     * Get contract eDocs related to a Property
     */
    public function getPropertyContractEDocs(Property $property, ?string $type = null): array
    {
        $contractRepository = $this->em->getRepository(Contract::class);
        $contracts = $contractRepository->findBy(['property' => $property]);
        
        $eDocs = [];
        foreach ($contracts as $contract) {
            $contractEDocs = $this->getEDocs($contract, $type);
            $eDocs = array_merge($eDocs, $contractEDocs);
        }
        
        return $eDocs;
    }

    private function getOwnerType(FileOwnerInterface $owner): string
    {
        $reflection = new ReflectionClass($owner);

        return $reflection->getShortName(); // Get short name here
    }
}
