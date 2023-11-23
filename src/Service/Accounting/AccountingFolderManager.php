<?php

namespace App\Service\Accounting;

use App\Exception\MissingAccountantFileException;
use App\Exception\MissingDocumentFileException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

use App\Entity\FinancialMonth;

/**
 * AccountingFolderManager is responsible for managing the files related to FinancialMonth entities.
 *
 * The methods provided by this service are:
 * - path building methods for the 'accounting' and 'accountant' folders.
 *   - ensureMonthFoldersExists
 *   - getAccountingFolderPath
 *   - getAccountantFolderPath
 * - file creation/deletion methods
 *  - createAccountingFile
 *  - deleteAccountingFile
 *  - hasAccountingFile
 */
class AccountingFolderManager
{
    public function __construct(
        private readonly string          $accountingFolder,
        private readonly string          $accountantFolder,
        private readonly Filesystem      $filesystem,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createAccountingFile(
        FinancialMonth $financialMonth,
        string         $sourcePath,
        string         $filename,
        ?bool          $isAttachment = false,
        ?bool          $overwriteExisting = false
    ): string {
        $destinationPath = $this->getAccountingFolderPath($financialMonth, $isAttachment) . '/' . $filename;
        if (is_dir($destinationPath)) {
            throw new Exception("The path $destinationPath is a directory");
        }
        if (is_file($destinationPath) and false === $overwriteExisting) {
            throw new Exception("The file $destinationPath already exists");
        }
        $this->filesystem->copy($sourcePath, $destinationPath);
        $this->logger->info("Created accounting file: $destinationPath");

        return $destinationPath;
    }

    /**
     * @throws Exception
     */
    public function createAnnotationFile(FinancialMonth $financialMonth, string $filename): string
    {
        $destinationPath = $this->getAccountingFolderPath($financialMonth, false) . '/' . $filename;
        if (is_dir($destinationPath)) {
            throw new Exception("The path $destinationPath is a directory");
        }
        if (is_file($destinationPath)) {
            throw new Exception("The file $destinationPath already exists");
        }
        // create an empty file with the given name
        $this->filesystem->touch($destinationPath);

        $this->logger->info("Created annotation file: $destinationPath");

        return $destinationPath;
    }

    /**
     * @throws Exception
     */
    public function deleteAccountingFile(FinancialMonth $financialMonth, string $filename, bool $isAttachment): void
    {
        $filePath = $this->getAccountingFolderPath($financialMonth, $isAttachment) . '/' . $filename;
        if (!is_file($filePath)) {
            throw new Exception("The file $filePath does not exist");
        }
        $this->filesystem->remove($filePath);
        $this->logger->info("Deleted accounting file: $filePath");
    }

    public function deleteAccountantFile(FinancialMonth $financialMonth, string $filename, bool $isAttachment): void
    {
        $filePath = $this->getAccountantFolderPath($financialMonth, $isAttachment) . '/' . $filename;
        if (!is_file($filePath)) {
            throw new MissingAccountantFileException($filePath);
        }
        $this->filesystem->remove($filePath);
        $this->logger->info("Deleted accountant file: $filePath");
    }

    public function syncAccountingFile(
        FinancialMonth $financialMonth,
        string         $filename,
        ?bool          $isAttachment = false
    ): void {
        $sourcePath = $this->getAccountingFolderPath($financialMonth, $isAttachment) . '/' . $filename;
        if (!is_file($sourcePath)) {
            throw new Exception("The file $sourcePath does not exist");
        }
        $targetPath = $this->getAccountantFolderPath($financialMonth, $isAttachment) . '/' . $filename;
        if (is_file($targetPath)) {
            $this->filesystem->remove($targetPath);
        }
        $this->filesystem->copy($sourcePath, $targetPath);
    }


    public function getAccountingFilePath(FinancialMonth $financialMonth, string $filename, bool $isAttachment): bool
    {
        try {
            $filePath = $this->getAccountingFolderPath($financialMonth, $isAttachment) . '/' . $filename;
            if (is_file($filePath)) {
                return $filePath;
            }
            throw new Exception("The file $filePath does not exist");
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAccountingFolderPath(
        FinancialMonth $financialMonth,
        bool           $isAttachment,
        ?bool          $absolute = true
    ): string {
        $path = $this->buildFolderPath($this->accountingFolder, $financialMonth, $isAttachment, $absolute);
        $this->ensureMonthFoldersExists(
            $this->buildFolderPath($this->accountingFolder, $financialMonth, $isAttachment, $absolute)
        );

        return $path;
    }

    public function getAccountantFolderPath(
        FinancialMonth $financialMonth,
                       $isAttachment,
        ?bool          $absolute = true
    ): string {
        $path = $this->buildFolderPath($this->accountantFolder, $financialMonth, $isAttachment, $absolute);
        $this->ensureMonthFoldersExists($path);

        return $path;
    }

    /**
     * Creates a folder for the given FinancialMonth entity if it doesn't exist
     */
    public function ensureMonthFoldersExists(string $path): void
    {
        $attachmentPath = $path . '/Anexos';
        if (!is_dir($attachmentPath)) {
            $this->filesystem->mkdir($path, 0755);
            $this->logger->info("Created directory for month: $attachmentPath");
        }
    }

    private function buildFolderPath(
        string         $rootFolder,
        FinancialMonth $financialMonth,
        bool           $isAttachment,
        ?bool          $absolute = true
    ): string {
        return sprintf('%s%s/%s%s',
            $absolute ? ($rootFolder . '/') : '',
            $financialMonth->getAccount()->getTenant()->getRfc(),
            $financialMonth->getPath(),
            $isAttachment ? '/Anexos' : '',
        );
    }
}