<?php

namespace App\Service\Strategies;

use Angle\CFDI\CFDIInterface;
use Angle\CFDI\XmlLoader;
use App\Service\DocumentSpecs\AttachmentSpecs;
use App\Service\DocumentSpecs\BaseDocumentSpecs;
use DateTime;
use DateTimeInterface;

use App\Exception\DocumentMatchException;
use App\Exception\DocumentParseException;

class StrategyHelper
{
    const MONTHS = [
        'ENERO',
        'FEBRERO',
        'MARZO',
        'ABRIL',
        'MAYO',
        'JUNIO',
        'JULIO',
        'AGOSTO',
        'SEPTIEMBRE',
        'OCTUBRE',
        'NOVIEMBRE',
        'DICIEMBRE'
    ];

    static public array $accountMap = [
        'MOPM670510J8A' => '1447391412',
        'MIJO620503Q60' => '1447302029',
    ];

    static public function getAccountNumber(?string $rfc): ?string
    {
        return self::$accountMap[$rfc] ?? null;
    }

    static public function isTenantRfc(string $rfc): bool
    {
        return null !== self::getAccountNumber($rfc);
    }

    /**
     * @throws DocumentParseException
     */
    public static function getCfdi(string $content, string $filePath): ?CFDIInterface
    {
        $loader = new XmlLoader();
        if (null === $cfdi = $loader->stringToCFDI($content)) {
            $errors  = $loader->getValidations();
            $isCfdi  = false;
            $message = 'check the code, this should not happen';
            foreach ($errors as $error) {
                if (str_starts_with($error['message'], 'XML is valid')) {
                    $isCfdi = true;
                }
                if (false === $error['success']) {
                    $message = $error['message'];
                }
            }
            if (!$isCfdi) {
                return null;
            }
            throw new DocumentParseException($filePath, $message);
        }

        return $cfdi;
    }

    /**
     * @throws DocumentMatchException
     */
    public static function extractMonthAndYearFromDescription(string $description, array $patterns): ?array
    {
        // each element in $pattern must contain a 'pattern', the month index and the year index
        foreach ($patterns as $triplet) {
            [$pattern, $monthIndex, $yearIndex] = $triplet;
            if (preg_match($pattern, $description, $matches)) {
                $month = $matches[$monthIndex];
                $month = in_array($month, self::MONTHS)
                    ? StrategyHelper::convertMonthToInt($month)
                    : $month;

                return [
                    $month,
                    (int)$matches[$yearIndex],
                ];
            }
        }
        throw new DocumentMatchException('Could not match month and year in description', '');
    }

    static public function monthAbbreviationToNumber(string $month): ?int
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

        return $months[strtoupper($month)] ?? null;
    }

    public static function extractBlock(string $pattern, string $content, int $width, int $lines): ?string
    {
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Get the offset of the matched string
            $startPos = $matches[0][1];
            // Count the number of new lines before the match
            $startLine = substr_count(substr($content, 0, $startPos), PHP_EOL);

            $contentLines = explode(PHP_EOL, $content);
            $extracted    = '';

            // Calculate the starting position relative to the beginning of the line where the match happened
            $lineStartPos = $startPos - strlen(implode(PHP_EOL, array_slice($contentLines, 0, $startLine))) - 1;

            for ($i = 0; $i < $lines; $i++) {
                if (isset($contentLines[$startLine + $i])) {
                    $line      = $contentLines[$startLine + $i];
                    $extracted .= substr($line, $lineStartPos, $width) . PHP_EOL;
                }
            }

            return trim(preg_replace('/\s+/', ' ', $extracted));
        }

        return null;
    }

    public static function convertToIntegerAmount(string $amount): int
    {
        // Remove commas
        $amount = str_replace([',', '$'], ['', ''], $amount);
        // Convert to float first, then multiply by 100 to keep cents as integers
        $amount = floatval($amount) * 100;

        // Cast to integer and return
        return (int)$amount;
    }

    public static function convertMonthToInt(string $monthName): int
    {
        return match (strtoupper($monthName)) {
            'ENERO'      => 1,
            'FEBRERO'    => 2,
            'MARZO'      => 3,
            'ABRIL'      => 4,
            'MAYO'       => 5,
            'JUNIO'      => 6,
            'JULIO'      => 7,
            'AGOSTO'     => 8,
            'SEPTIEMBRE' => 9,
            'OCTUBRE'    => 10,
            'NOVIEMBRE'  => 11,
            'DICIEMBRE'  => 12,
        };
    }

    public static function convertCFEDate(string $dateString): DateTimeInterface
    {
        $months = [
            'ENE' => 'JAN',
            'FEB' => 'FEB',
            'MAR' => 'MAR',
            'ABR' => 'APR',
            'MAY' => 'MAY',
            'JUN' => 'JUN',
            'JUL' => 'JUL',
            'AGO' => 'AUG',
            'SEP' => 'SEP',
            'OCT' => 'OCT',
            'NOV' => 'NOV',
            'DIC' => 'DEC'
        ];

        $englishDateString = str_replace(array_keys($months), array_values($months), $dateString);

        return DateTime::createFromFormat('d M y', $englishDateString);

    }

    public static function reduceSpaces(string $content): string
    {
        return preg_replace('/\s+/', ' ', $content);
    }

    /**
     * @throws DocumentParseException
     */
    public static function extractValues(string $pattern, string $content, string $filePath, $concept): array
    {
        if (false !== preg_match($pattern, $content, $matches)) {
            array_shift($matches);
            if (count($matches) > 0) {
                return $matches;
            }
        }
        throw new DocumentMatchException($concept, $filePath);
    }

    /**
     * @throws DocumentParseException
     */
    public static function extractValue(string $pattern, string $content, string $filePath, $concept): string
    {
        return self::extractValues($pattern, $content, $filePath, $concept)[0];
    }

    public static function formatFromCfdi(string $template, array $cfdiData): string
    {
        foreach ($cfdiData as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            $template = str_replace("#$key#", $value, $template);
        }

        return $template;
    }

    public static function getCfdiData(?CFDIInterface $cfdi): array
    {
        $item = $cfdi->getItemList()->getItems()[0];

        return [
            'series'        => $cfdi->getSeries(),
            'folio'         => $cfdi->getFolio(),
            'amount'        => (int)round($cfdi->getTotal() * 100),
            'issuerRfc'     => $cfdi->getIssuerRfc(),
            'issuerName'    => $cfdi->getIssuer()->getName(),
            'recipientRfc'  => $cfdi->getRecipientRfc(),
            'recipientName' => $cfdi->getRecipient()->getName(),
            'issueDate'     => $cfdi->getDate(),
            'description'   => $item->getDescription(),
        ];
    }

    public static function getSpecialCase(?string $filePath): ?BaseDocumentSpecs
    {
        $specialCases = [
            'MOPM670510J8ACCC0000000108.pdf'                                            => [204623, 2018, 9],
            'MOPM670510J8ACCC0000000184.pdf'                                            => [300000, 2019, 1],
            'MOPM670510J8ACCC0000000207.pdf'                                            => [300000, 2019, 2],
            'MOPM670510J8ACCC0000000263.pdf'                                            => [300000, 2019, 4],
            'MOPM670510J8ACCC0000000332.pdf'                                            => [300000, 2019, 6],
            'FCAP0000000083.pdf'                                                        => [300000, 2019, 9],
            'Linea de Captura Septiembre 2018.pdf'                                      => [null, 2018, 9],
            'Linea de Captura Diciembre 2018.pdf'                                       => [null, 2018, 12],
            'Linea de Captura Anual 2020 Mayela Monrroy.pdf'                            => [1563400, 2020, null],
            '07.2019 Acuse linea de captura Julio 2019 324121532 Mayela.pdf'            => [757800, 2019, 7],
            '07.2019 Detalle Declaracion  Julio 2019 324121532 Mayela.pdf'              => [757800, 2019, 7],
            'Detalle de la declaracion 2021 - Mayela Monroy.pdf'                        => [null, 2021, null],
            'Opinion al cumplimiento de obligaciones fiscales.pdf'                      => [null, 2018, 12],
            'PCA0312011M3_Recibo de Pago_4751_8F8EB65C-AEA7-4689-BF99-400D969C9B33'     => [501795, null, null, 'PAB'],
            'PCA0312011M3_Recibo de Pago_4749_C61E80AF-B804-4154-A46A-200EA06A31CA.pdf' => [501795, 2019, 1, 'PAB'],
            'PCA0312011M3_Recibo de Pago_4751_8F8EB65C-AEA7-4689-BF99-400D969C9B33.pdf' => [501795, 2019, 2, 'PAB'],
            'PCA0312011M3_Recibo de Pago_5503_2D5B7DE6-94E7-4158-8145-D8740C114BB6.pdf' => [23195, null, null, 'PAB'],
        ];
        foreach ($specialCases as $filename => $specs) {
            if (str_ends_with($filePath, $filename)) {
                [$amount, $year, $month, $propertyKey] = array_pad($specs, 4, null);;

                return (new AttachmentSpecs())
                    ->setDisplayFilename('Gasto Contabilidad ' . $year . '-' . $month . '.pdf')
                    ->setAmount($amount)
                    ->setPropertyKey($propertyKey)
                    ->setYear($year)
                    ->setMonth($month)
                    ->setAccountNumber('1447391412');
            }
        }

        foreach ($specialCases as $filename => $specs) {
            if (str_ends_with($filePath, $filename)) {
                return $specs;
            }
        }

        return null;
    }
}