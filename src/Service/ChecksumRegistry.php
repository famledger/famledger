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
    public function load(?string $subFolder = null): self
    {
        $subFolder ??= $this->rootFolder;
        if (!str_starts_with($subFolder, $this->rootFolder)) {
            throw new Exception(sprintf('Folder %s is not in root folder %s', $subFolder, $this->rootFolder));
        }

        $this->checksums  = [];
        $this->duplicates = [];
        $finder           = new Finder();
        $finder->files()->in($subFolder);
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

        return $this;
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