<?php

namespace App\Service\Detectors;

use App\Exception\DocumentParseException;
use App\Service\DocumentDetector\DocumentDetectorInterface;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\Strategies\StrategyInterface;

abstract class BaseDetector implements DocumentDetectorInterface
{
    private array              $strategies;
    private ?DetectionProtocol $detectionProtocol = null;

    abstract public function getStrategies(): array;

    public function __construct()
    {
        $this->strategies = $this->getStrategies();
    }

    public function setProtocol(DetectionProtocol $detectionProtocol): void
    {
        $this->detectionProtocol = $detectionProtocol;
    }

    /**
     * @throws DocumentParseException
     */
    public function detect(string $content, ?string $filePath = null, ?string $originalName = null): ?BaseDocumentSpecs
    {
        foreach ($this->strategies as $strategy) {
            /** @var StrategyInterface $strategy */

            $strategyClass = get_class($strategy); // Get the class name of the strategy

            $matches = $strategy->matches($content, $filePath);
            $this->detectionProtocol->addStrategy(get_class($this), $strategyClass, $matches);
            if ($matches) {
                $documentSpecs = $strategy->parse($content, $filePath);

                $filename = $strategy->suggestFilename($documentSpecs, $originalName ?? $filePath);
                $documentSpecs->setSuggestedFilename($filename);

                return $documentSpecs;
            }
        }

        return null;
    }
}
