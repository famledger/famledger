<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use DateTime;
use DateTimeZone;
use Exception;

use App\Entity\Customer;
use App\Enum\RegimenFiscal;
use App\Enum\UsoCfdi;
use App\Service\LiveModeContext;

class InvoiceRequestComposer
{
    private ?array $template;
    private string $invoiceMode;

    /**
     * @throws Exception
     */
    public function __construct(string $invoiceMode, LiveModeContext $liveModeContext)
    {
        if (!in_array($invoiceMode, ['produccion', 'debug'])) {
            throw new Exception('Invalid invoice mode. Must be "produccion" or "debug"');
        }

        $liveMode          = ($liveModeContext->getLiveMode() and $invoiceMode === 'produccion');
        $this->invoiceMode = $liveMode ? 'produccion' : 'debug';
    }

    /**
     * @throws Exception
     */
    public function setTemplate(array $template): self
    {
        if (!isset($template['CFDi'])) {
            throw new Exception('Invalid template');
        }
        $this->template = $template;
        $currentDate    = (new DateTime('now', new DateTimeZone('America/Mexico_City')))
            ->format('Y-m-d H:i:s');

        $this->setSection('fechaEmision', $currentDate);

        $this->template['CFDi']['Impuestos'] = [
            'Totales'   => ['retenciones' => 0, 'traslados' => 0],
            'Impuestos' => []
        ];

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getInvoiceRequest(): array
    {
        if (!isset($this->template['CFDi'])) {
            throw new Exception('Invalid template');
        }
        $this->setSection('modo', $this->invoiceMode);

        $this->template['CFDi']['Impuestos']['Totales']['retenciones'] = sprintf('%.2f',
            $this->template['CFDi']['Impuestos']['Totales']['retenciones']);
        $this->template['CFDi']['Impuestos']['Totales']['traslados']   = sprintf('%.2f',
            $this->template['CFDi']['Impuestos']['Totales']['traslados']);

        return $this->template;
    }

    public function setFolio(string $folio): self
    {
        return $this->setSection('folioInterno', $folio);
    }

    public function setSerie(string $serie): self
    {
        return $this->setSection('serie', $serie);
    }

    public function setSubstitution(?Invoice $substitutedInvoice): self
    {
        if (null !== $substitutedInvoice) {
            $this->setSection('ComprobantesRelacionados', [
                [
                    'tipoRelacion' => '04',
                    'Comprobantes' => [
                        $substitutedInvoice->getSeries() . '-' . $substitutedInvoice->getNumber()
                    ]
                ]
            ]);
        }

        return $this;
    }

    /**
     * "rfc": "XAXX010101000"
     * "nombre": "Público en general"
     * "usoCfdi": "S01"
     * "regimenFiscal": "616"
     */
    public function setRecipient(Customer $customer, UsoCfdi $usoCfdi, RegimenFiscal $regimenFiscal): self
    {
        $receptor                  = $this->getSection('Receptor');
        $receptor['rfc']           = $customer->getRfc();
        $receptor['nombre']        = $customer->getName();
        $receptor['usoCfdi']       = $usoCfdi->value;
        $receptor['regimenFiscal'] = $regimenFiscal->value;

        return $this->setSection('Receptor', $receptor);
    }

    public function setUsoCfdi(string $usoCfdi): self
    {
        $receptor            = $this->getSection('Receptor');
        $receptor['usoCfdi'] = $usoCfdi;

        return $this->setSection('Receptor', $receptor);
    }

    public function setRegimeType(string $regimeType): self
    {
        $receptor                  = $this->getSection('Receptor');
        $receptor['regimenFiscal'] = $regimeType;

        return $this->setSection('Receptor', $receptor);
    }

    public function setDescription(string $description): self
    {
        $partidas                   = $this->getSection('Partidas');
        $partidas[0]['descripcion'] = $description;

        return $this->setSection('Partidas', $partidas);
    }

    public function setCuentaPredial(string $cuentaPredial): self
    {
        $partidas                     = $this->getSection('Partidas');
        $partidas[0]['CuentaPredial'] = ['numero' => [$cuentaPredial]];

        return $this->setSection('Partidas', $partidas);
    }

    private function getSection(string $section): mixed
    {
        return $this->template['CFDi'][$section] ?? null;
    }

    private function setSection(string $section, mixed $data): self
    {
        $this->template['CFDi'][$section] = $data;

        return $this;
    }

    public function setEmails(Customer $customer): self
    {
        // TODO: implement customer emails and sending
        $emails = $customer->getEmails();
        if (is_array($emails) and count($emails)) {
            $this->template['CFDi']['EnviarCFDI'] = [
                'Correos'       => $emails,
                'mensajeCorreo' => '',
            ];
        }

        return $this;
    }

    public function setPaymentOptions(string $paymentMethod, string $paymentForm): self
    {
        $this->template['CFDi']['DatosDePago'] = [
            'metodoDePago' => $paymentMethod,
            'formaDePago'  => $paymentForm
        ];

        return $this;
    }

    public function setAmount(float $subTotal, float $totalAmount): self
    {
        $this->template['CFDi']['subTotal'] = round($subTotal, 2);
        $this->template['CFDi']['total']    = round($totalAmount, 2);

        $partidas                     = $this->getSection('Partidas');
        $partidas[0]['importe']       = round($subTotal, 2);
        $partidas[0]['valorUnitario'] = round($subTotal, 2);

        return $this->setSection('Partidas', $partidas);
    }

    public function setTaxes(array $taxes): self
    {
        $this->setSection('Impuestos', $taxes);

        $partidas                 = $this->getSection('Partidas');
        $partidas[0]['Impuestos'] = $taxes['Impuestos'];

        return $this->setSection('Partidas', $partidas);
    }
}