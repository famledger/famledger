<?php

namespace App\Exception;

use Exception;

use App\Service\ChecksumHelper;

class ExistingFileException extends Exception
{
    public function __construct(string $filepath)
    {
        $checksum = ChecksumHelper::get(file_get_contents($filepath));
        parent::__construct(sprintf("The file '%s' already exists. checksum=%s", $filepath, $checksum));
    }
}
