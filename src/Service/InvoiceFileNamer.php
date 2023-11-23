<?php

namespace App\Service;

use App\Constant\InvoiceStatus;
use App\Entity\Invoice;

/**
 * The InvoiceFileNamer is in charge of providing both
 * - the name displayed in the UI for income documents which are created under the ACCOUNTING_FOLDER
 * - the name of the physical invoice files which are stored in the INVOICES_FOLDER
 * Both files are identical and only differ by their name and location. The file in the accounting folder
 * is created as a copy of the invoice file in the invoice folder when the invoice is associated with a statement.
 *
 * The filename of invoices (they are stored in the INVOICES_FOLDER), is built from the invoice number, series,
 * recipient name, and property slug and is appended by the status of the invoice if it has been cancelled.
 *
 * The filename displayed in the statement UI differs slightly from the file in the corresponding accounting folder.
 * The accounting folder file is prepended by the sequence number of the transaction which is done by the
 * InvoiceFolderManager.
 */
class InvoiceFileNamer
{
    /**
     * Returns the file name of the document as displayed in the statement.
     */
    static public function getInvoiceDocumentName(Invoice $invoice, ?string $extension = 'pdf'): string
    {
        return 'Ingreso Factura ' . self::buildFilenameStem($invoice) . ".$extension";
    }

    static public function getInvoiceFilename(Invoice $invoice): string
    {
        return self::buildFilenameStem($invoice) . '.pdf';
    }

    static public function buildFilenameStem(Invoice $invoice): string
    {
        // 813-A-2023-08 Hector Guzman OF6
        // 813-A - Travel Ten PLAYACAR
        $numberPart    = $invoice->getNumber() . '-' . $invoice->getSeries();
        $datePart      = (null === $invoice->getYear() or $invoice->getTenant()->getRfc() === 'MIJO620503Q60')
            ? ''
            : sprintf('-%s-%02d', $invoice->getYear(), $invoice->getMonth());
        $recipientPart = $invoice->getCustomer()->getName();
        $propertyPart  = $invoice->getProperty()?->getSlug() ?? ''; // TODO: use 'getAsset()?->getSlug' once Property has been abstracted
        $status        = $invoice->getStatus();

        return trim(sprintf('%s%s%s %s %s%s',
            false === $invoice->getLiveMode() ? '_' : '',
            $numberPart,    // '813-A'
            $datePart,      // '-2023-08' (optional)
            $recipientPart, // 'Hector Guzman' | 'Travel Ten'
            $propertyPart,   // 'OF6' | 'PLAYACAR'
            InvoiceStatus::VIGENTE === $status ? '' : '- ' . $status
        ));
    }
}