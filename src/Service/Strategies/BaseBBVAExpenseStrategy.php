<?php

namespace App\Service\Strategies;

use DateTime;
use Exception;

use App\Exception\DocumentParseException;
use App\Service\DocumentSpecs\ExpenseSpecs;

abstract class BaseBBVAExpenseStrategy implements StrategyInterface
{
    abstract protected function specificMatchLogic(array $properties, string $content): bool;

    protected function getPropertyKey(): ?string
    {
        return null;
    }

    protected array $properties      = [];
    private array   $propertyMapping = [
        'Cuenta de retiro'   => ['Cuenta de retiro', 'Cuenta de cargo', 'Cuenta de Cargo'],
        'Cuenta destino'     => ['Cuenta destino', 'Cuenta asociada'],
        'Fecha de operacion' => ['Fecha de operación', 'Fecha y hora de pago', 'Fecha y Hora de Pago'],
        'Importe'            => ['Importe', 'Importe pagado', 'Importe Pagado'],
    ];

    public function matches(string $content, ?string $filePath = null): bool
    {
        $this->properties = [];

        // old receipts may not contain 'BBVA' but contain 'Transferir - Otros bancos - Cuenta CLABE'
        if (
            (!str_contains($content, 'BBVA') and !str_contains($content, 'Transferir - Otros bancos - Cuenta CLABE'))
            or !str_contains($content, 'Comprobante')) {
            return false;
        }
        $this->extractProperties($content);

        return $this->specificMatchLogic($this->properties, $content);
    }

    private function extractProperties(string $content): void
    {
        preg_match_all('/^\s*(.*?):\s*(.*?)\s*$/m', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key                    = trim($match[1]);
            $value                  = trim($match[2]);
            $this->properties[$key] = $value;
        }
        $this->normalizeProperties();
    }

    private function normalizeProperties(): void
    {
        foreach ($this->propertyMapping as $standard => $alternatives) {
            $this->properties[$standard] = $this->findPropertyByAlternatives($alternatives);
        }
        $this->properties = array_filter($this->properties);
    }

    private function findPropertyByAlternatives(array $alternatives)
    {
        foreach ($alternatives as $alt) {
            if (isset($this->properties[$alt])) {
                return $this->properties[$alt];
            }
        }

        return null;
    }

    /**
     * @throws DocumentParseException
     * @throws Exception
     */
    protected function getExpenseData(string $content, ?string $filePath = null): array
    {
        [$day, $month, $year] = StrategyHelper::extractValues('/(\d{2})\/(\d{2})\/(\d{4})/i',
            $this->properties['Fecha de operacion'] ?? '',
            $filePath,
            'Fecha'
        );
        $month = in_array($month, StrategyHelper::MONTHS)
            ? StrategyHelper::convertMonthToInt($month)
            : (int)$month;

        $amount         = StrategyHelper::convertToIntegerAmount($this->properties['Importe']);
        $depositAccount = $this->properties['Cuenta destino'] ?? null;
        $accountNumber  = $this->properties['Cuenta de retiro'];

        if ('Arrendam' === $accountNumber) {
            $accountNumber = '1447391412';
        }

        return [
            'month'          => $month,
            'year'           => (int)$year,
            'amount'         => -1 * $amount,
            'accountNumber'  => $accountNumber,
            'depositAccount' => $depositAccount,
            'issueDate'      => new DateTime(sprintf('%d-%02d-%02d', $year, $month, $day)),
        ];
    }

    /**
     * @throws DocumentParseException
     * @throws Exception
     */
    public function parse(string $content, ?string $filePath = null): ExpenseSpecs
    {
        return new ExpenseSpecs(array_merge(
            ['propertyKey' => 'BBVA'],
            $this->getExpenseData($content, $filePath)
        ));
    }
}
