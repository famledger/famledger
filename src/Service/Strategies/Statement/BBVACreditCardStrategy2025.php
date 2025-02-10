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

class BBVACreditCardStrategy2025 implements StrategyInterface
{
    public function matches(string $content, ?string $filePath = null): bool
    {
        return str_contains($content, 'TARJETA PLATINUM BBVA (PLATINUM)')
               and str_contains($content, 'Número de tarjeta: 4772143019386206');
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

        $cellContent = StrategyHelper::extractBlock('/Periodo:/', $content, 80, 1);

        preg_match('/Periodo:\s+(\d{2}-\w{3}-\d{4})\s+al\s+(\d{2}-\w{3}-\d{4})/i', $cellContent, $matches);

        $startDate = $matches[1];
        // $endDate   = $matches[2];
        [, $month, $year] = explode('-', $startDate);
        $year = strlen($year) === 2 ? 2000 + (int)$year : (int)$year;
        $statement
            ->setMonth(StrategyHelper::monthAbbreviationToNumber($month))
            ->setYear(($year));

        // Cuenta CLABE                                                   012975474576269367
        preg_match('/CLABE: (\d{18})/', $content, $matches);
        $statement->setAccountNumber($matches[1]);

        // Extract starting balance
        preg_match('/Saldo deudor total.*\\$([\d,.]+)/', $content, $matches);
        if (isset($matches[1])) {
            $statement->setStartingBalance($this->convertToIntegerAmount($matches[1]));
        }
        $statement->setEndingBalance(0);

        return $statement;
    }

    private function parseTransactions(string $pdfText, int $year): array
    {
        $lines         = explode("\n", $pdfText);
        $transactions  = [];
        $columnIndexes = null;
        $status        = 'idle';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                continue;
            }

            // Detect the start of the transactions section
            if ($status === 'idle' && str_contains($trimmed, 'CARGOS,COMPRAS Y ABONOS')) {
                $status = 'started';
                continue;
            }

            // Detect the end of the transactions section
            if ($status === 'started' && $trimmed == 'NOTAS ACLARATORIAS') {
                $status = 'stopped';
                break;
            }

            // Skip lines outside the transactions section
            if ($status !== 'started') {
                continue;
            }

            // Detect main transaction lines by matching two leading dates and an ending amount
            if (preg_match('/^\s+(\d{2}-\w{3}-\d{4})\s+(\d{2}-\w{3}-\d{4}).*([+-]\s?\$[\d,.]+)$/', $line, $matches,
                PREG_OFFSET_CAPTURE)) {
                $columnIndexes = [
                    'bookingDate' => $matches[1][1],
                    'valueDate'   => $matches[2][1],
                    'description' => (int)$matches[2][1] + strlen($matches[2][0]),
                    'amount'      => $matches[3][1]
                ];

                $bookingDate = $this->convertToDateTime($matches[1][0], $year);
                $valueDate   = $this->convertToDateTime($matches[2][0], $year);
                $description = substr(
                    $line,
                    $columnIndexes['description'],
                    $columnIndexes['amount'] - $columnIndexes['description']
                );
                $amount      = $this->convertToIntegerAmount($matches[3][0]);

                $transactions[] = [
                    'bookingDate' => $bookingDate,
                    'valueDate'   => $valueDate,
                    'description' => trim($description),
                    'amount'      => $amount,
                ];
            } elseif ($columnIndexes) {
                // Handle continuation lines by extracting the description
                $beforeDescription = substr($line, 0, $columnIndexes['description']);
                $afterDescription  = substr($line, $columnIndexes['amount']);

                if (trim($beforeDescription) !== '' || trim($afterDescription) !== '') {
                    // Abort if there is anything other than spaces before or after the description column
                    continue;
                }

                $description = substr(
                    $line,
                    $columnIndexes['description'],
                    $columnIndexes['amount'] - $columnIndexes['description']
                );

                $transactions[count($transactions) - 1]['description'] .= ' ' . trim($description);
            }
        }

        return $transactions;
    }

    public function convertToIntegerAmount(string $amount): int
    {
        // Determine if the amount is negative
        $isNegative = str_contains($amount, '-');

        // Remove dollar sign, plus or minus sign, and commas
        $amount = str_replace(['$', '+', '-'], '', $amount);
        $amount = str_replace(',', '', $amount);

        // Convert to float and multiply by 100 to keep cents as integers
        $amount = floatval($amount) * 100;

        // Cast to integer and apply sign
        return $isNegative ? -(int)$amount : (int)$amount;
    }

    public function convertToDateTime(string $date, int $year): ?DateTime
    {
        // Split the date by hyphen
        $dateParts = explode('-', $date);

        if (count($dateParts) !== 3) {
            return null; // Invalid date format
        }

        $day      = intval($dateParts[0]);
        $monthStr = strtoupper($dateParts[1]);
        $month    = StrategyHelper::monthAbbreviationToNumber($monthStr);

        if (null === $month) {
            return null; // Invalid month abbreviation
        }

        $year = intval($dateParts[2]);

        return DateTime::createFromFormat('Y-m-d', sprintf('%d-%02d-%02d', $year, $month, $day));
    }
}
