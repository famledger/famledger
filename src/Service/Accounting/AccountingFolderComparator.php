<?php

namespace App\Service\Accounting;

use App\Service\ChecksumHelper;

/**
 * AccountingFolderComparator is responsible for comparing files in two given
 * accounting folders based on their names and content checksums.
 *
 * - The class uses the ChecksumHelper for checksum calculations.
 * - It provides functionalities to load files from the given directories, process them,
 *   and extract relevant checksum information.
 * - The results can be retrieved by content checksum or by name checksum.
 * - Additionally, there's a utility method to create a new instance of the class
 *   and another one to reload its internal state.
 */
class AccountingFolderComparator
{
    private array $sourceFiles       = [];
    private array $targetFiles       = [];
    private array $byContentChecksum = [];
    private array $byNameChecksum    = [];

    private function __construct(
        private readonly string $path1,
        private readonly string $path2
    ) {
        $this->init();
    }

    public static function create(string $path1, string $path2): self
    {
        return new self($path1, $path2);
    }

    public function getByContentChecksum(): array
    {
        return $this->byContentChecksum;
    }

    public function getByNameChecksum(): array
    {
        return $this->byNameChecksum;
    }

    public function reload(): self
    {
        $this->init();

        return $this;
    }

    private function init(): void
    {
        $this->assertFolder($this->path1);
        $this->sourceFiles = $this->loadFiles($this->path1);
        $this->assertFolder($this->path2);
        $this->targetFiles = $this->loadFiles($this->path2);
        $this->processLoadedFiles();
    }

    private function loadFiles(string $path): array
    {
        $files = [];
        foreach (scandir($path) as $file) {
            if (in_array($file, ['.', '..', '.DS_Store'])) {
                continue;
            }
            if (is_file($path . '/' . $file)) {
                $checksum = (filesize($path . '/' . $file) === 0)
                    ? ChecksumHelper::get($file)
                    : ChecksumHelper::get(file_get_contents($path . '/' . $file));

                $creationDate = filectime($path . '/' . $file);
                $files[$file] = new FileModel($file, $checksum, $creationDate);
            }
        }

        return $files;
    }

    private function processLoadedFiles(): void
    {
        foreach ([$this->sourceFiles, $this->targetFiles] as $index => $fileData) {
            foreach ($fileData as $fileModel) {
                /** @var FileModel $fileModel */
                $folderIndex     = $index === 0 ? 'source' : 'target';
                $contentChecksum = $fileModel->getChecksum();
                $nameChecksum    = ChecksumHelper::get($fileModel->getName());

                if (!isset($this->byContentChecksum[$contentChecksum]['source'])) {
                    $this->byContentChecksum[$contentChecksum]['source'] = [];
                }
                if (!isset($this->byContentChecksum[$contentChecksum]['target'])) {
                    $this->byContentChecksum[$contentChecksum]['target'] = [];
                }

                $this->byContentChecksum[$contentChecksum][$folderIndex][] = $fileModel;
                $this->byNameChecksum[$nameChecksum][$folderIndex]         = $contentChecksum;
            }
        }
        foreach ($this->byNameChecksum as $nameChecksum => $data) {
            if (count($data) === 1 or $data['source'] === $data['target']) {
                unset($this->byNameChecksum[$nameChecksum]);
            } else {
                // a file with this name checksum exists in both folders
                // we can extract the filename by looking up the content checksum in the array of source files
                $filename = null;
                foreach ($this->sourceFiles as $filename => $fileModel) {
                    if ($fileModel->getChecksum() === $data['source']) {
                        $filename = $fileModel->getName();
                        break;
                    }
                }
                // replace the content checksum with the filename, we only need to display the filename
                $this->byNameChecksum[$nameChecksum] = $filename;
            }
        }
    }

    private function assertFolder(string $path1): void
    {
        if (!is_dir($path1)) {
            mkdir($path1, 0777, true);
        }
    }
}
