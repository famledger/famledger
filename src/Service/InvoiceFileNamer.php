<?php

namespace App\Service;

use App\Constant\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\Receipt;

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
 *
 * The InvoiceFileNamer is responsible for generating the filenames for invoice documents in two different contexts:
 * 1. For display in the user interface (UI) within the statement section.
 * 2. For physical storage in the 'invoices' directory.
 *
 * The class handles naming for invoice files created under the ACCOUNTING_FOLDER and the INVOICES_FOLDER.
 * While the files in both folders are identical in content, they differ in their naming and storage location.
 * The files in the ACCOUNTING_FOLDER are copies of the invoice files in the INVOICES_FOLDER,
 * created when an invoice is associated with a statement.
 *
 * Naming Scheme:
 * - In INVOICES_FOLDER: Filename is constructed using the invoice number, series, recipient name, property slug,
 *   and appended by the invoice status if it has been cancelled.
 * - In ACCOUNTING_FOLDER: Filename is prepended with 'Ingreso Factura '.
 *
 * Example:
 * For an invoice with number '813', series 'A', recipient 'Hector Guzman', property slug 'OF6',
 * the filename in INVOICES_FOLDER would be depending on the status
 *  - '813-A-2023-08 Hector Guzman OF6.pdf'
 *  - '813-A-2023-08 Hector Guzman OF6 - cancelado.pdf'
 * and in ACCOUNTING_FOLDER, it would be
 *  - 'Ingreso Factura 813-A-2023-08 Hector Guzman OF6.pdf'
 *  - 'Ingreso Factura 813-A-2023-08 Hector Guzman OF6 - cancelado.pdf'
 */
class InvoiceFileNamer
{
    /**
     * Returns the file name of the document as displayed in the statement.
     */
    static public function buildDocumentName(Invoice $invoice, ?string $extension = 'pdf'): string
    {
        return ($invoice instanceof Receipt
                ? 'Recibo de Pago '
                : 'Ingreso Factura '
               ) . self::buildFileName($invoice, $extension);
    }

    static public function buildFileName(Invoice $invoice, string $extension): string
    {
        // 813-A-2023-08 Hector Guzman OF6
        // 813-A - Travel Ten PLAYACAR
        $numberPart    = $invoice->getNumber() . '-' . $invoice->getSeries();
        $datePart      = (null === $invoice->getYear() or $invoice->getTenant()->getRfc() === 'MIJO620503Q60')
            ? ''
            : sprintf('-%s-%02d', $invoice->getYear(), $invoice->getMonth());
        $recipientPart = $invoice->getCustomer()->getName();
        $propertyPart  = $invoice->getProperty()?->getSlug() ?? ''; // TODO: use 'getAsset()?->getSlug' once Property has been abstracted
        $status        = strtolower($invoice->getStatus());

        return trim(sprintf('%s%s%s %s%s%s.%s',
            false === $invoice->getLiveMode() ? '_' : '',
            $numberPart,    // '813-A'
            $datePart,      // '-2023-08' (optional)
            $recipientPart, // 'Hector Guzman' | 'Travel Ten'
            empty($propertyPart) ? '' : " $propertyPart",   // 'OF6' | 'PLAYACAR'
            InvoiceStatus::VIGENTE === $status ? '' : ' - ' . $status,
            $extension
        ));
    }
}