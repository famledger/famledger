<?php

namespace App\Service\Invoice;

use App\Entity\Customer;

class ReceiptRequestBuilder extends BaseRequestBuilder
{
    public function __construct()
    {
        parent::__construct();
        $this->request['tipoMoneda']  = 'XXX';
        $this->request['total']       = 0;
        $this->request['subTotal']    = 0;
        $this->request['Partidas']    = [
            'agrupador'        => 'Recibo Electrónico de Pagos',
            'cantidad'         => '1',
            'claveUnidad'      => 'ACT',
            'unidad'           => 'Actividad',
            'claveProdServ'    => '84111506',
            'descripcion'      => 'Pago',
            'valorUnitario'    => '0',
            'importe'          => '0',
            'objetoDeImpuesto' => '01'
        ];
        $this->request['exportacion'] = '01';
    }

    public function setCustomer(Customer $customer, string $invoiceUsage): self
    {
        return parent::setCustomer($customer, 'CP01');
    }

    public function setComplementoPago(ComplementoPago $complementoPago): self
    {
        return $this;
    }
}