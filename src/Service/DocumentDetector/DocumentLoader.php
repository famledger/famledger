<?php

namespace App\Service\DocumentDetector;

use App\Exception\DocumentConversionException;
use App\Exception\DocumentDetectionException;
use App\Service\Detectors\DetectionProtocol;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\PDFConverter;
use App\Service\Strategies\StrategyHelper;

class DocumentLoader
{
    public function __construct(
        private readonly DocumentDetectorRegistry $registry,
        private readonly PDFConverter             $pdfConverter,
        private readonly DetectionProtocol        $detectionProtocol
    ) {
    }

    /**
     * @throws DocumentDetectionException
     * @throws DocumentConversionException
     */
    public function load(string $filePath, ?string $format = null, ?string $originalName = null): ?BaseDocumentSpecs
    {
        if(StrategyHelper::getSpecialCase($filePath) !== null) {
            return StrategyHelper::getSpecialCase($filePath);
        }

        if (null === $format) {
            $format = $this->determineFileFormat($originalName ?? $filePath);
        }

        $content = match ($format) {
            'pdf'   => $this->pdfConverter->pdfToText($filePath, $originalName),
            'txt',
            'xml'   => file_get_contents($filePath),
            default => throw new DocumentConversionException(sprintf('Unsupported file format: %s', $format)),
        };

        $format = match ($format) {
            'pdf',
            'txt'   => 'text',
            'xml'   => 'xml',
            default => $format,
        };

        $this->detectionProtocol->initFile($filePath, new \DateTime());
        // Loop through all registered detectors that support the given format.
        foreach ($this->registry->getDetectors($format) as $detector) {
            $this->detectionProtocol->addDetector(get_class($detector));
            $detector->setProtocol($this->detectionProtocol);
            $documentSpecs = $detector->detect($content, $filePath, $originalName);
            if ($documentSpecs !== null) {
                return $documentSpecs;
            }
        }


        // If no detector could identify the document, throw an exception.
        throw new DocumentDetectionException('The document type could not be detected: ' . $filePath);
    }

    public function getDetectionProtocol(): DetectionProtocol
    {
        return $this->detectionProtocol;
    }

    private function determineFileFormat(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_EXTENSION);
    }
}
