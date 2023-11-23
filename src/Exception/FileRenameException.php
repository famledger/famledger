<?php

namespace App\Exception;

use Exception;

class FileRenameException extends Exception
{
    public function __construct(string $sourceFilepath, string $targetFilepath)
    {
        parent::__construct(sprintf('Could not rename %s to %s',
            $sourceFilepath,
            $targetFilepath
        ));
    }
}
