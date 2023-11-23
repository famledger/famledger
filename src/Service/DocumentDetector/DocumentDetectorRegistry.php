<?php

namespace App\Service\DocumentDetector;

use App\Constant\DocumentType;
use App\Service\Detectors\BaseDetector;

class DocumentDetectorRegistry
{
    private array $detectors = [];

    public function addDetector(DocumentDetectorInterface $detector): void
    {
        $this->detectors[] = $detector;
    }

    /**
     * @return DocumentDetectorInterface[]
     */
    public function getDetectors(string $format): array
    {
        $detectors = [];
        foreach ($this->detectors as $detector) {
            if ($detector->supportsFormat($format)) {
                $detectors[] = $detector;
            }
        }

        usort($detectors, function ($a, $b) {
            /** @var BaseDetector $a */
            /** @var BaseDetector $b */
            $priorityA = DocumentType::getPriority($a->supportsType());
            $priorityB = DocumentType::getPriority($b->supportsType());

            return $priorityA <=> $priorityB;
        });

        return $detectors;
    }

    public function getDebugConfig(): array
    {
        $detectors = [];

        foreach ($this->detectors as $detector) {
            $detectors[get_class($detector)] = $detector->getStrategies();
        }

        return $detectors;
    }
}
