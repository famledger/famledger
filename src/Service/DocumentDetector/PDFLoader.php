<?php

namespace App\Service\DocumentDetector;

use Throwable;

use App\Exception\DocumentConversionException;
use App\Exception\DocumentDetectionException;
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\PDFConverter;

class PDFLoader
{
    private ?StatementSpecs $documentSpecs = null;

    public function __construct(
        private readonly DocumentDetectorRegistry $registry,
        private readonly PDFConverter             $pdfConverter
    ) {
    }

    /**
     * @throws DocumentDetectionException
     * @throws DocumentConversionException
     */
    public function loadPDF(string $pdfPath, ?bool $saveTextFile = false): void
    {
        $html = $this->pdfConverter->pdfToText($pdfPath);
        if ($saveTextFile) {
            file_put_contents('/tmp/' . basename($pdfPath) . '.txt', $html);
        }
        $this->loadHTML($html);
    }

    /**
     * @throws DocumentDetectionException
     */
    public function loadHTML(string $htmlContent): void
    {
        foreach ($this->registry->getDetectors() as $detector) {
            try {
                if (null !== ($this->documentSpecs = $detector->detect($htmlContent))) {
                    return;
                }
            } catch (Throwable) {
            }
        }
        throw new DocumentDetectionException('The document specs could not be detected.');
    }

    public function getDocumentSpecs(): ?StatementSpecs
    {
        return $this->documentSpecs;
    }
}
