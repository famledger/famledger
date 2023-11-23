<?php

namespace App\Service;

use Exception;

use App\Constant\DocumentType;

class DocumentHelper
{
    /**
     * @throws Exception
     */
    static public function getTypeFromFilename(string $fileName): DocumentType
    {
        if (str_ends_with($fileName, '.txt')) {
            return DocumentType::ANNOTATION;
        }

        $statementPrefixes = [
            '00 ',
            'Estado de Cuenta',
            'Nomina 2885949823 ',
            'Mayela Monroy Personal 1447253494 ',
            'Estado de Cuenta Familia 1447271220 '
        ];

        foreach ($statementPrefixes as $prefix) {
            if (str_starts_with($fileName, $prefix)) {
                return DocumentType::ACCOUNT_STATEMENT;
            }
        }

        if (str_starts_with($fileName, '00 ') or str_starts_with($fileName, 'Estado de Cuenta')) {
            return DocumentType::ACCOUNT_STATEMENT;
        }
        if (preg_match('/^\d{2} Gasto/', $fileName)) {
            return DocumentType::EXPENSE;
        }
        if (preg_match('/^\d{2} Pago Impuesto[s]* \d{4}-\d{2}/', $fileName)) {
            return DocumentType::TAX;
        }
        if (preg_match('/^\d{2} Ingreso /', $fileName)) {
            return DocumentType::INCOME;
        }

        return DocumentType::ANNOTATION;
    }
}