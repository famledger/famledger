<?php

namespace App\Service\Strategies;

use Angle\CFDI\CFDIInterface;
use Exception;

use App\Exception\DocumentParseException;

abstract class BaseCfdiStrategy implements StrategyInterface
{
    protected ?CFDIInterface $cfdi = null;

    public function matches(string $content, ?string $filePath = null): bool
    {
        // Reset $cfdi in case it's been set from a previous call
        $this->cfdi = null;

        try {
            $this->cfdi = StrategyHelper::getCfdi($content, $filePath);
            if (null === $this->cfdi) {
                return false;
            }
            return $this->specificMatchLogic($this->cfdi);
        } catch (Exception $e) {
            if($e instanceof DocumentParseException) {
                throw $e;
            }
            return false;
        }
    }

    abstract protected function specificMatchLogic(?CFDIInterface $cfdi): bool;
}