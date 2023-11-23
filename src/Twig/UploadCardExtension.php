<?php

namespace App\Twig;

use ReflectionClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\FileOwnerInterface;

class UploadCardExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('upload_card', [$this, 'uploadCard'], ['is_safe' => ['html']]),
            new TwigFilter('upload_url', [$this, 'uploadUrl'], ['is_safe' => ['html']])
        ];
    }

    public function uploadCard(FileOwnerInterface $owner, string $type, string $caption, ?int $width = 4): string
    {
        $ownerId    = $owner->getId();
        $reflection = new ReflectionClass($owner);
        $ownerName  = $reflection->getShortName();
        $url        = $this->buildUrl($ownerName, $ownerId, $type);

        return <<<HTML
    <div class="col-md-$width">
        <div class="card">
            <div class="card-header bg-light bg-success">$caption</div>
            <div class="card-body">
                <form action="$url" class="e-doc dropzone"></form>
            </div>
        </div>
    </div>
HTML;
    }

    public function uploadUrl(FileOwnerInterface $owner, string $type): string
    {
        $ownerId    = $owner->getId();
        $reflection = new ReflectionClass($owner);
        $ownerName  = $reflection->getShortName();

        return $this->buildUrl($ownerName, $ownerId, $type);
    }

    private function buildUrl(string $ownerName, int $ownerId, string $type): string
    {
        return $this->urlGenerator->generate('admin_eDoc_upload', [
            'ownerName' => $ownerName,
            'ownerId'   => $ownerId,
            'type'      => $type,
        ]);
    }
}