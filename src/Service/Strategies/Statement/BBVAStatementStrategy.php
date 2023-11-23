<?php

namespace App\Service\Strategies\Statement;

use DateTime;
use InvalidArgumentException;

use App\Entity\Statement;
use App\Entity\Transaction;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use App\Service\DocumentSpecs\StatementSpecs;
use App\Service\Strategies\StrategyInterface;

class BBVAStatementStrategy implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'Estado de Cuenta')
               and preg_match('/No\. de Cliente/', $content);
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
            $transaction = (new Transaction())
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

        preg_match('/Periodo\s+DEL\s+(\d{2}\/\d{2}\/\d{4})\s+AL\s+(\d{2}\/\d{2}\/\d{4})/', $content, $matches);
        $startDate = $matches[1];
        // $endDate   = $matches[2];
        [, $month, $year] = explode('/', $startDate);
        $statement
            ->setMonth($month)
            ->setYear($year);

        // Extracting additional fields
        preg_match('/No. de Cuenta\s+(\d+)/', $content, $matches);
        $statement->setAccountNumber($matches[1]);
        preg_match('/Saldo Anterior\s+([\d,.]+)/', $content, $matches);
        $statement->setStartingBalance($this->convertToIntegerAmount($matches[1]));
        preg_match('/Saldo Final\s+([\d,.]+)/', $content, $matches);
        $statement->setEndingBalance($this->convertToIntegerAmount($matches[1]));
        preg_match('/Depósitos \/ Abonos \(\+\)\s+(\d+)/', $content, $matches);
        $statement->setNoDeposits((int)$matches[1]);
        preg_match('/Retiros \/ Cargos \(-\)\s+(\d+)/', $content, $matches);
        $statement->setNoWithdrawals((int)$matches[1]);

        return $statement;
    }

    private function parseTransactions(string $pdfText, int $year): array
    {
        $lines = explode("\n", $pdfText); // Assuming the PDF text is in $pdfText

        $status             = 'idle';
        $transactions       = [];
        $currentTransaction = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($status === 'idle' && preg_match('/OPER\s+LIQ\s+DESCRIPCION/', $trimmed)) {
                $status = 'started';
                continue;
            }

            if ($status === 'started' && str_contains($trimmed, 'Total de Movimientos')) {
                $status = 'stopped';
                break;
            }

            if ($status === 'started' && $trimmed) {
                if (preg_match('/(\d{2}\/\w{3})\s+(\d{2}\/\w{3})/', $line,
                    $matches)) { // match: e.g.  05/DIC       05/DIC
                    $bookingDate = $this->convertToDateTime($matches[1], $year);
                    $valueDate   = $this->convertToDateTime($matches[2], $year);
                    // replace all characters in $line that correspond to $matches[0] with dots, so they are treated as a single
                    // term when splitting by spaces
                    $startPos = strpos($line, $matches[0]);
                    $length   = strlen($matches[0]);
                    $line     = substr_replace($line, str_repeat('.', $length), $startPos, $length);
                    $parts    = $this->splitBySpaces($line);
                    // parts[0] contains the the description, parts[1] contains the amount and parts[2] contains the balance
                    // parts[2] may not be present in rare situations so we need to check whether parts[1] is a valid amount too
                    if (!isset($parts[2]) and !$this->isValidAmount($parts[1])) {
                        continue;
                    }
                    $currentTransaction = [
                        'bookingDate' => $bookingDate,
                        'valueDate'   => $valueDate,
                        'description' => trim($parts[1]),
                        'amount'      => $this->extractAmount(trim($parts[2] ?? $parts[1]), $line),
                    ];
                    $transactions[]     = $currentTransaction;
                } elseif (preg_match('/^\s{10,}/', $line)) {
                    // Likely a continuation line
                    $additionalParts                        = $this->splitBySpaces($trimmed);
                    $currentTransaction['description']      .= ' ' . implode(' ', $additionalParts);
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

        $day      = intval($dateParts[0]);
        $monthStr = strtoupper($dateParts[1]);

        if (!isset($months[$monthStr])) {
            return null; // Invalid month string
        }

        $month = $months[$monthStr];

        return DateTime::createFromFormat('Y-m-d', sprintf('%d-%02d-%02d', $year, $month, $day));
    }

    private function isValidAmount(string $amount): bool
    {
        // re remove the thousands separator which is a comma and make sure the rest is numeric
        // e.g. 1,000.00 -> 1000.00
        return is_numeric(str_replace(',', '', $amount));
    }
}