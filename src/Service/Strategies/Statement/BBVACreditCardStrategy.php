<?php

namespace App\Service\Strategies\Statement;

use DateTime;
use InvalidArgumentException;

use App\Entity\PaymentTransaction;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\Strategies\StrategyHelper;
use App\Service\Strategies\StrategyInterface;

class BBVACreditCardStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'No. de Tarjeta')
               and str_contains($content, '4772 1430 1938 6206');
    }

    public function suggestFilename(BaseDocumentSpecs $documentSpecs, ?string $filePath = null): string
    {
        if (!$documentSpecs instanceof StatementSpecs) {
            throw new InvalidArgumentException('Invalid document specs');
        }

        return sprintf('Estado de Cuenta %s %d-%02d.pdf',
            $documentSpecs->getAccountNumber(),
            $documentSpecs->getYear(),
            $documentSpecs->getMonth()
        );
    }

    public function parse(string $content, ?string $filePath = null): ?BaseDocumentSpecs
    {
        $statement = $this->createFromText($content);

        return (new StatementSpecs())
            ->setYear($statement->getYear())
            ->setMonth($statement->getMonth())
            ->setAccountNumber($statement->getAccountNumber())
            ->setStatement($statement);
    }

    public function createFromText($text): Statement
    {
        $statement = $this->parseStatement($text);
        $year      = $statement->getYear();

        foreach ($this->parseTransactions($text, $year) as $idx => $transactionData) {
            $transaction = $transactionData['amount'] > 0
                ? new PaymentTransaction()
                : new Transaction();
            $transaction
                ->setStatement($statement)
                ->setSequenceNo($idx + 1)
                ->setBookingDate($transactionData['bookingDate'])
                ->setValueDate($transactionData['valueDate'])
                ->setDescription($transactionData['description'])
                ->setAmount($transactionData['amount']);
            $statement->addTransaction($transaction);
        }

        return $statement;
    }

    private function parseStatement($content): Statement
    {
        $statement = new Statement();

        $cellContent = StrategyHelper::extractBlock('/ {5}Periodo/', $content, 40, 4);

        preg_match('/Periodo\s+Del\s+(\d{2}\/\d{2}\/\d{2})\s+al\s+(\d{2}\/\d{2}\/\d{2})/i', $cellContent, $matches);
        $startDate = $matches[1];
        // $endDate   = $matches[2];
        [, $month, $year] = explode('/', $startDate);
        $year = strlen($year) === 2 ? 2000 + (int)$year : (int)$year;
        $statement
            ->setMonth($month)
            ->setYear($year);

        // Cuenta CLABE                                                   012975474576269367
        preg_match('/Cuenta CLABE\s+(\d+)/', $content, $matches);
        $statement->setAccountNumber($matches[1]);

        //Saldo Inicial del Periodo            -$            31,034.85
        preg_match('/Saldo Anterior\s+([\d,.]+)/', $content, $matches);
        $statement->setStartingBalance($this->convertToIntegerAmount($matches[1]));

        // Saldo al Corte                       $             44,623.74
        preg_match('/Saldo Nuevo\s+([\d,.]+)/', $content, $matches);
        $statement->setEndingBalance($this->convertToIntegerAmount($matches[1]));

        return $statement;
    }

    private function parseTransactions(string $pdfText, int $year): array
    {
        $lines = explode("\n", $pdfText); // Assuming the PDF text is in $pdfText

        $status                = 'idle';
        $transactions          = [];
        $currentTransaction    = [];
        $isMainTransactionLine = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($status === 'idle' && preg_match('/Movimientos Efectuados/', $trimmed)) {
                $status = 'started';
                continue;
            }

            if ($status === 'started' && str_contains($trimmed, 'Resumen Informativo de Beneficios')) {
                $status = 'stopped';
                break;
            }

            if ($status === 'started' && $trimmed) {
                $parts                 = $this->splitByColumns($line);
                $isSecondLine          = ($isMainTransactionLine and !empty($parts[2]) and empty($parts[0]) and empty($parts[1]) and empty($parts[3]) and empty($parts[4]));
                $isMainTransactionLine = (isset($parts[4]) and preg_match('/\*{5}[0-9]{4}/', $parts[4]));
                if (!$isMainTransactionLine and !$isSecondLine) {
                    continue;
                }

                if ($isMainTransactionLine) {
                    $bookingDate = $this->convertToDateTime($parts[0], $year);
                    $valueDate   = $this->convertToDateTime($parts[1], $year);
                    // parts[2] contains the description, parts[5] contains the amount and parts[3] contains the RFC
                    if (!isset($parts[2]) and !$this->isValidAmount($parts[5])) {
                        continue;
                    }
                    $amount = empty($parts[5])
                        ? -1 * $this->convertToIntegerAmount($parts[6])
                        : $this->convertToIntegerAmount($parts[5]);

                    $rfc                = str_replace(' ', '', $parts[3]);
                    $currentTransaction = [
                        'bookingDate' => $bookingDate ?? $valueDate,
                        'valueDate'   => $valueDate,
                        'description' => $parts[2] . (empty($rfc) ? '' : (' | ' . $rfc)),
                        'rfc'         => $rfc,
                        'amount'      => $amount,
                    ];
                    $transactions[]     = $currentTransaction;
                } else {
                    // Likely a continuation line
                    // split up the RFC added in main transaction line

                    $currentTransaction['description']      = sprintf('%s %s | %s',
                        preg_replace('/\|.+$/', '', $currentTransaction['description']),
                        $parts[2],
                        $currentTransaction['rfc']
                    );
                    $transactions[count($transactions) - 1] = $currentTransaction;
                }
            }
        }

        return $transactions;
    }

    private function extractAmount(string $amountText, string $line): int
    {
        $position   = strpos($line, $amountText);
        $isNegative = ($position < 117);  // Change 50 to the actual position where negative amounts start
        $amount     = $this->convertToIntegerAmount($amountText);

        return $isNegative ? -$amount : $amount;
    }

    public function convertToIntegerAmount(string $amount): int
    {
        // Remove commas
        $amount = str_replace(',', '', $amount);
        // Convert to float first, then multiply by 100 to keep cents as integers
        $amount = floatval($amount) * 100;

        // Cast to integer and return
        return (int)$amount;
    }

    function splitBySpaces($string): array
    {
        return preg_split('/\s{2,}/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    public function convertToDateTime(string $date, int $year): ?DateTime
    {
        // Mapping Spanish abbreviated month names to numbers
        $months = [
            'ENE' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'ABR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AGO' => 8,
            'SEP' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DIC' => 12,
        ];

        $dateParts = explode('/', $date);

        if (count($dateParts) < 2) {
            return null; // Invalid date format
        }

        $day = intval($dateParts[0]);
        if (strlen($dateParts[1]) === 3) {
            $monthStr = strtoupper($dateParts[1]);
            if (!isset($months[$monthStr])) {
                return null;
            }
            $month = $months[$monthStr];
        } else {
            $month = intval($dateParts[1]);
        }

        return DateTime::createFromFormat('Y-m-d', sprintf('%d-%02d-%02d', $year, $month, $day));
    }

    private function isValidAmount(string $amount): bool
    {
        // re remove the thousands separator which is a comma and make sure the rest is numeric
        // e.g. 1,000.00 -> 1000.00
        return is_numeric(str_replace(',', '', $amount));
    }

    private function splitByColumns(string $line): array
    {
        $columnStartIndexes = [6, 27, 43, 96, 122, 136, 155];
        $columnEndIndexes   = [13, 35, 94, 123, 133, 150, 170];
        $columns            = [];
        for ($i = 0; $i < count($columnStartIndexes); $i++) {
            $start     = $columnStartIndexes[$i];
            $length    = isset($columnEndIndexes[$i])
                ? $columnEndIndexes[$i] - $start + 1
                : strlen($line) - $start;
            $columns[] = trim(substr($line, $start, $length));
        }

        return $columns;
    }
}
