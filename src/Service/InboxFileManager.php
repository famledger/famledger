<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

class InboxFileManager
{
    public function __construct(
        private readonly string        $inboxFolder,
        private readonly TenantContext $tenantContext
    ) {
    }

    public function getFiles(?bool $fullPath = false): array
    {
        $files           = [];
        $inboxFolderPath = $this->getOrCreateInboxFolderPath();

        $fileFinder = new Finder();
        $fileFinder->files()->in($inboxFolderPath)
            ->name('/\.(xml|pdf|html|txt)$/')
            ->sortByName();

        foreach ($fileFinder as $file) {
            $filename = $fullPath ? $file->getPathname() : $file->getFilename();
            $files[]  = $filename;
        }

        return $files;
    }

    public function deleteFile(string $filename): void
    {
        $inboxFolderPath = $this->getOrCreateInboxFolderPath();
        $filePath        = sprintf('%s/%s', $inboxFolderPath, $filename);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function getInboxFolderPath(): string
    {
        $tenantRfc = $this->tenantContext->getTenant()->getRfc();

        return $this->inboxFolder . '/' . $tenantRfc;
    }

    private function getOrCreateInboxFolderPath(): string
    {
        $inboxFolderPath = $this->getInboxFolderPath();
        $this->createDirIfNotExists($inboxFolderPath);

        return $inboxFolderPath;
    }

    private function createDirIfNotExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
