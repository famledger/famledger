<?php

namespace App\Service;

use App\Exception\DocumentConversionException;

class PDFConverter
{
    /**
     * @throws DocumentConversionException
     */
    public function pdfToText(string $pdfPath, ?string $originalName = null): string
    {
//        TODO: remove this once we're sure we don't need it, it was used to read only the first page of legacy documents
//        $command = str_contains(($originalName ?? $pdfPath), 'Estado de Cuenta')
//            ? sprintf("pdftotext -layout '%s' -", $pdfPath)
//            : sprintf("pdftotext -f 1 -l 1 -layout '%s' -", $pdfPath);

        $output     = [];
        $return_var = null; // distinguish null from 0 success status code

        $command = sprintf("pdftotext -layout '%s' -", $pdfPath);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new DocumentConversionException('PDF conversion failed with status code ' . $return_var);
        }

        return implode("\n", $output);
    }
}
