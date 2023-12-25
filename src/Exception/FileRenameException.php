<?php

namespace App\Exception;

use Exception;

class FileRenameException extends Exception
{
    public function __construct(string $sourceFilepath, string $targetFilepath, string $message)
    {
        parent::__construct(sprintf('Could not rename %s to %s. Error: %s',
            $sourceFilepath,
            $targetFilepath,
            $message
        ));
    }
}
