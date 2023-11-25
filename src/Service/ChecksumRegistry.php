<?php

namespace App\Service;

use Exception;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class ChecksumRegistry
{
    private array $checksums  = [];
    private array $duplicates = [];

    public function __construct(
        private readonly string $rootFolder,
    ) {
    }

    /**
     * @throws Exception
     */
    public function loadFolder(string $folder): void
    {
        if (!str_starts_with($folder, $this->rootFolder)) {
            throw new Exception(sprintf('Folder %s is not in root folder %s', $folder, $this->rootFolder));
        }

        $finder = new Finder();
        $finder->files()->in($folder);
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $filepath = $file->getPathname();
            if (filesize($filepath) === 0) {
                continue;
            }

            $checksum = ChecksumHelper::get(file_get_contents($filepath));
            $subDir   = substr($filepath, strlen($this->rootFolder));
            if (isset($this->checksums[$checksum])) {
                $this->duplicates[$checksum][$subDir][] = $filepath;
            } else {
                $this->checksums[$checksum] = $filepath;
            }
        }
        $loaded = 1;
    }

    public function get(string $checksum): ?string
    {
        return $this->checksums[$checksum] ?? null;
    }
    public function getDuplicates(): array
    {
        return $this->duplicates;
    }
}