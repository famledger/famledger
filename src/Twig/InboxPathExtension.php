<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Service\InboxFileManager;

class InboxPathExtension extends AbstractExtension
{
    public function __construct(
        private readonly InboxFileManager $inboxFileManager
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('inbox_folder_path', [$this, 'inboxFolderPath'], ['is_safe' => ['html']]),
        ];
    }

    public function inboxFolderPath(): string
    {
        return $this->inboxFileManager->getInboxFolderPath();
    }
}