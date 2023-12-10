<?php

namespace App\Service;

use App\Constant\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\Tenant;
use App\Exception\InvoiceException;

/**
 * Manages the storage and retrieval of invoice files.
 *
 * This service handles the operations related to invoice file management
 * including storing new PDF and XML invoice files, updating existing files,
 * and constructing file paths based on invoice data.
 *
 * The service checks for changes in the invoice checksum to determine if
 * the files should be updated, ensuring that only the latest files are stored.
 * It is capable of throwing exceptions when file retrieval or storage fails,
 * which allows for robust error handling and reporting in the application.
 *
 * NOTICE: this service assumes that the URLs of the remote files are available
 *         in the invoice entity under the properties 'urlPdf' and 'urlXml' and
 *         will throw an exception if they are not.
 */
class InvoiceFileManager
{
    public function __construct(
        private readonly string $invoicesFolder,
    ) {
    }

    /**
     * Fetches new content for invoice files and updates them if needed.
     *
     * This method checks if the PDF and XML files of an invoice need to be updated
     * by comparing the checksum of the current file content with the checksum stored
     * in the invoice entity. If the checksum has changed, the method fetches the
     * new content from the URLs provided in the invoice entity and stores the new content.
     *
     * If the invoice entity does not contain URLs for either the PDF or XML files,
     * an InvoiceException is thrown.
     *
     * The method also throws an InvoiceException if fetching the content from the URLs fails
     * or if storing the fetched content to the local filesystem fails.
     *
     * @throws InvoiceException
     */
    public function fetchOrUpdateInvoiceFiles(Invoice $invoice): void
    {
        if (null === $urlPdf = $invoice->getUrlPdf()) {
            throw new InvoiceException($invoice, 'Invoice has no PDF URL');
        }
        if (null === $urlXml = $invoice->getUrlXml()) {
            throw new InvoiceException($invoice, 'Invoice has no XML URL');
        }

        // Update the PDF file if the checksum has changed
        $pdfContent = file_get_contents($urlPdf);
        if ($pdfContent === false) {
            throw new InvoiceException($invoice, "Failed to fetch PDF content from $urlPdf");
        }
        $pdfChecksum = ChecksumHelper::get($pdfContent);
        if (false === $this->storePDF($invoice, $pdfContent)) {
            throw new InvoiceException($invoice, "Failed to store PDF content for {$invoice->getNumber()}");
        }
        $invoice->setChecksumPdf($pdfChecksum);
        if ($pdfChecksum !== $invoice->getChecksumPdf()) {
        }

        // Update the XML file if the checksum has changed
        $xmlContent = file_get_contents($urlXml);
        if ($xmlContent === false) {
            throw new InvoiceException($invoice, "Failed to fetch XML content from $urlXml");
        }
        $invoice->setCfdi($xmlContent);
        $xmlChecksum = ChecksumHelper::get($xmlContent);
        if (false === $this->storeXML($invoice, $xmlContent)) {
            throw new InvoiceException($invoice, "Failed to store XML content for {$invoice->getNumber()}");
        }
        $invoice->setChecksumXml($xmlChecksum);
        if ($xmlChecksum !== $invoice->getChecksumXml()) {
        }
    }


    public function getPdfPath(Invoice $invoice): string
    {
        return $this->getInvoiceFolder($invoice) . '/' . $this->getPdfFilename($invoice);
    }

    public function getXmlPath(Invoice $invoice): string
    {
        return $this->getInvoiceFolder($invoice) . '/' . $this->getXmlFilename($invoice);
    }

    public function getPdfFilename(Invoice $invoice): string
    {
        return InvoiceFileNamer::buildFileName($invoice, 'pdf');
    }

    public function getXmlFilename(Invoice $invoice): string
    {
        return InvoiceFileNamer::buildFileName($invoice, 'xml');
    }


    private function storePDF(Invoice $invoice, string $content): int|false
    {
        return $this->store($content, $this->getPdfPath($invoice));
    }

    private function storeXML(Invoice $invoice, bool|string $xmlContent): int|false
    {
        return $this->store($xmlContent, $this->getXmlPath($invoice));
    }

    private function store(string $content, string $filepath): int|false
    {
        $invoiceFolder = dirname($filepath);

        if (!is_dir($invoiceFolder)) {
            mkdir($invoiceFolder, 0777, true);
        }

        return file_put_contents($filepath, $content);
    }

    public function getInvoiceFolder(Invoice $invoice): string
    {
        return sprintf('%s/%s/%s',
            $this->invoicesFolder,
            $invoice->getTenant()->getRfc(),
            $invoice->getSeries()
        );
    }
}
