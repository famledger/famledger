<?php

namespace App\Service\Invoice;

use DateTime;
use Exception;

use App\Entity\Invoice;
use App\Entity\Account;

class ComplementoPago
{
    private array $data;
    private array $totals;

    /**
     * @throws Exception
     */
    public function __construct(
        DateTime $paymentDate,
        Account  $originatorAccount,
        Account  $beneficiaryAccount,
        string   $paymentMethod,
        array    $invoices
    ) {
        $this->totals = [
            'retencionesIVA'         => null,
            'retencionesISR'         => null,
            'trasladosBaseIVA16'     => null,
            'trasladosImpuestoIVA16' => null,
            'montoTotalPagos'        => null,
        ];

        $this->taxes['ISR']['retencion'] = null;

        $this->data = [
            'Totales' => [],
            'Pago'    => [
                'fechaPago'              => $paymentDate->format('Y-m-d H:i:s'),
                'formaDePago'            => $paymentMethod,
                'tipoMoneda'             => 'MXN',
                'tipoCambio'             => 1,
                'rfcCtaOrden'            => $originatorAccount->getBankRfc(),
                'nombreBancoOrdExt'      => $originatorAccount->getBankName(),
                'ctaOrdenante'           => $originatorAccount->getNumber(),
                'rfcEmisorCtaBen'        => $beneficiaryAccount->getBankRfc(),
                'nombreBancoCtaBen'      => $beneficiaryAccount->getBankName(),
                'ctaBeneficiario'        => $beneficiaryAccount->getNumber(),
                'monto'                  => null, // TODO: must calculate
                'Impuestos'              => [],   // TODO: must calculate
                'DocumentosRelacionados' => [],
            ],
        ];
        foreach ($invoices as $invoice) {
            /** @var Invoice $invoice */
            if ('PPD' !== strtoupper($invoice->getPaymentMethod())) {
                throw new Exception('Only invoices with payment method PPD can have receipts.');
            }
            $this->addInvoice($invoice);
        }
    }

    public function addInvoice(Invoice $invoice): self
    {
        $amount    = sprintf('%.2f', $invoice->getAmount());
        $peticion  = $invoice->getPeticion();
        $impuestos = array_map(function (array $data) use ($peticion) {

            return [
                'baseImpuesto'  => $peticion['CFDI']['subTotal'],
                'claveImpuesto' => $data['claveImpuesto'],
                'importe'       => $data['importe'],
                'tasaOCuota'    => $data['tasaOCuota'],
                'tipo'          => $data['tipo'],
                'tipoFactor'    => $data['tipoFactor'],
            ];
        }, $peticion['CFDI']['Impuestos']['Impuestos']);

        $this->data['Pago']['DocumentosRelacionados'][] = [
            'idDocumento'       => $invoice->getFolioFiscalUUID(),
            'serie'             => $invoice->getSeries(),
            'folioInterno'      => $invoice->getNumber(),
            'tipoMoneda'        => 'MXN',
            'equivalencia'      => '1',
            'numParcialidad'    => '1',
            'saldoAnterior'     => $amount,
            'importePagado'     => $amount,
            'impoSaldoInsoluto' => '0.00',
            'objetoDeImpuesto'  => '02', // TODO: understand and verify if always the same
            'Impuestos'         => $impuestos
        ];

        $map = [
            'ISR-retencion'=>'retencionesISR',
            'IVA-retencion'=>'retencionesIVA',
            'IVA-traslado'=>'retencionesIVA',
            'retencionesIVA'         => null,
            'retencionesISR'         => null,
            'trasladosBaseIVA16'     => null,
            'trasladosImpuestoIVA16' => null,
            'montoTotalPagos'        => null,
        ];
        foreach($impuestos as $impuesto) {
            $clave = $impuesto['claveImpuesto'];
            $tipo = $impuesto['tipo'];

            switch("$clave-$tipo") {
                case 'ISR-retencion':
                    $this->totals['retencionesIVA'] += $impuesto['importe'];
                    break;
                case 'IVA-retencion':
                    $this->totals['retencionesISR'] += $impuesto['importe'];
                    break;
                case 'IVA-traslado':
                    $this->totals['trasladosBaseIVA16']     += $impuesto['baseImpuesto'];
                    $this->totals['trasladosImpuestoIVA16'] += $impuesto['importe'];
                    break;
                default:
                    throw new Exception("Unknown tax $clave-$tipo");
            }
            $this->totals['trasladosBaseIVA16']     += $impuesto['baseImpuesto'];
            $this->totals['trasladosImpuestoIVA16'] += $impuesto['importe'];

        }
        return $this;
    }

    public function getData(): array
    {
        $this->data['Totales'] = $this->getTotals();

        return ['ComplementoPago' => $this->data];
    }
}