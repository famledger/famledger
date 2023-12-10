<?php

namespace App\Service\Invoice;

use DateTime;

use App\Entity\Customer;

abstract class BaseRequestBuilder
{
    protected array $request = [];

    public function __construct()
    {
        $this->request = [
            'nombreDisenio' => 'Diseño predeterminado',
            'tipoMoneda'    => 'MXN',
            'fechaEmision'  => (new DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    public function getRequest(): array
    {
        return ['CFDi' => $this->request];
    }

    public function setSeries(string $series): self
    {
        $this->request['serie'] = $series;

        return $this;
    }

    public function setNumber(int $number): self
    {
        $this->request['folioInterno'] = $number;

        return $this;
    }

    public function setCustomer(Customer $customer, string $invoiceUsage): self
    {
        $address                   = $customer->getDefaultAddress();
        $this->request['Receptor'] = array_filter([
            'rfc'             => $customer->getRfc(),
            'nombre'          => $customer->getName(),
            'usoCfdi'         => $invoiceUsage,
            'regimenFiscal'   => $customer->getRegimenFiscal()->value,
            'DomicilioFiscal' => null === $address
                ? null
                : [
                    'calle'      => $address->getCalle(),
                    'noExterior' => $address->getNoExterior(),
                    'noInterior' => $address->getNoInterior(),
                    'colonia'    => $address->getColonia(),
                    'localidad'  => $address->getLocalidad(),
                    'municipio'  => $address->getMunicipio(),
                    'estado'     => $address->getEstado(),
                    'pais'       => $address->getPais(),
                    'cp'         => $address->getCp(),
                ]
        ]);

        return $this;
    }
}