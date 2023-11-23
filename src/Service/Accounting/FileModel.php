<?php

namespace App\Service\Accounting;

class FileModel
{
    public function __construct(
        private readonly string $name,
        private readonly string $checksum,
        private readonly int    $creationDate
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getCreationDate(): int
    {
        return $this->creationDate;
    }
}
