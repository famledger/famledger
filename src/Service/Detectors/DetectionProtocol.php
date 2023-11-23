<?php

namespace App\Service\Detectors;

use DateTime;
use DateTimeInterface;

class DetectionProtocol
{
    private array $protocol = [];

    public function initFile(string $fileName, DateTime $timestamp): void
    {
        $this->protocol['file']      = $fileName;
        $this->protocol['timestamp'] = $timestamp->format(DateTimeInterface::ATOM);
        $this->protocol['detectors'] = [];
    }

    public function addDetector(string $detectorName): void
    {
        $this->protocol['detectors'][] = [
            'name'          => $detectorName,
            'strategies'    => [],
            'parsingResult' => null,
        ];
    }

    public function addStrategy(string $detectorName, string $strategyName, bool $matches): void
    {
        $detectorKey = $this->findDetector($detectorName);
        if ($detectorKey !== null) {
            $this->protocol['detectors'][$detectorKey]['strategies'][] = [
                'name'    => $strategyName,
                'matches' => $matches,
            ];
        }
    }

    public function addParsingResult(string $detectorName, bool $success, string $details): void
    {
        $detectorKey = $this->findDetector($detectorName);
        if ($detectorKey !== null) {
            $this->protocol['detectors'][$detectorKey]['parsingResult'] = [
                'success' => $success,
                'details' => $details,
            ];
        }
    }

    public function getProtocol(): array
    {
        return $this->protocol;
    }

    private function findDetector(string $detectorName): ?int
    {
        foreach ($this->protocol['detectors'] as $key => $detector) {
            if ($detector['name'] === $detectorName) {
                return $key;
            }
        }

        return null;
    }
}
