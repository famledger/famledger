<?php

namespace App\EventListener;

use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Event\Invoice\InvoiceDetailsFetchedEvent;
use App\Service\AddressService;

#[AsEventListener(event: InvoiceDetailsFetchedEvent::class, method: 'onInvoiceCreated')]
class AddressEventListener
{
    public function __construct(
        private readonly AddressService $addressService
    ) {
    }

    /**
     * @throws Exception
     */
    public function onInvoiceCreated(InvoiceDetailsFetchedEvent $event): void
    {
        $invoice = $event->getInvoice();
        if (null === $customer = $invoice->getCustomer()) {
            return;
        }
        if (null === $data = $invoice->getData('peticion')) {
            return;
        }
        $peticion = json_decode($data, true);
        if (null === $domicilioFiscal = ($peticion['CFDi']['Receptor']['DomicilioFiscal'] ?? null)) {
            return;
        }

        $this->addressService->createAddressIfNotExists($domicilioFiscal, $customer);
    }
}