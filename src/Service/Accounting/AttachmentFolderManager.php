<?php

namespace App\Service\Accounting;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

use App\Entity\Account;
use App\Exception\ExistingFileException;

/**
 * AttachmentFolderManager is responsible for managing the files related to attachments.
 *
 * The methods provided by this service are:
 * - path building methods for the attachment folder.
 *   - ensureAttachmentFoldersExists
 *   - getAttachmentFolderPath
 */
class AttachmentFolderManager
{
    public function __construct(
        private readonly string          $attachmentsRootFolder,
        private readonly Filesystem      $filesystem,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createAttachmentFile(
        Account $account,
        string  $sourcePath,
        string  $filename,
        ?bool   $overwriteExisting = false
    ): string {
        $destinationPath = $this->getAttachmentFolderPath($account) . '/' . $filename;
        if (is_dir($destinationPath)) {
            throw new Exception("The path $destinationPath is a directory");
        }
        if (is_file($destinationPath) and false === $overwriteExisting) {
            throw new ExistingFileException($destinationPath);
        }
        $this->filesystem->copy($sourcePath, $destinationPath);
        $this->logger->info("Created accounting file: $destinationPath");

        return $destinationPath;
    }

    public function getAttachmentFolderPath(Account $account, ?bool $absolute = true): string
    {
        $path = $this->buildFolderPath($this->attachmentsRootFolder, $account, $absolute);
        $this->ensureAttachmentFoldersExists($this->buildFolderPath($this->attachmentsRootFolder, $account, $absolute));

        return $path;
    }

    public function ensureAttachmentFoldersExists(string $path): void
    {
        $attachmentPath = $path . '/Anexos';
        if (!is_dir($attachmentPath)) {
            $this->filesystem->mkdir($path, 0755);
            $this->logger->info("Created directory for month: $attachmentPath");
        }
    }

    private function buildFolderPath(string $rootFolder, Account $account, ?bool $absolute = true): string
    {
        return sprintf('%s%s/%s',
            $absolute ? ($rootFolder . '/') : '',
            $account->getTenant()->getRfc(),
            $account->getNumber()
        );
    }
}