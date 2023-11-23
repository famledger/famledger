<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Entity\Customer;
use App\Event\Invoice\InvoiceCreatedEvent;
use App\Service\TenantContext;
use App\Repository\CustomerRepository;

#[AsEventListener(event: InvoiceCreatedEvent::class, method: 'onInvoiceCreated')]
class CustomerEventListener
{
    private array $customerCache = [];

    public function __construct(
        private readonly CustomerRepository     $customerRepo,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext          $tenantContext
    ) {
    }

    /**
     * @throws Exception
     */
    public function onInvoiceCreated(InvoiceCreatedEvent $event): void
    {
        $invoice = $event->getInvoice();
        $rfc     = $invoice->getRecipientRFC();
        if (null === $customer = $this->getCustomerFromRfc($rfc)) {
            $customer = (new Customer())
                ->setRfc($rfc)
                ->setName($invoice->getRecipientName())
                ->setTenant($this->tenantContext->getTenant());

            $this->customerCache[$rfc] = $customer;

            $this->em->persist($customer);
        }
        $customer->addInvoice($invoice);
    }

    private function getCustomerFromRfc(string $rfc): ?Customer
    {
        if (isset($this->customerCache[$rfc])) {
            return $this->customerCache[$rfc];
        }
        $customer = $this->customerRepo->findOneBy(['rfc' => $rfc]);
        if (null === $customer) {

            return null;
        }
        $this->customerCache[$rfc] = $customer;

        return $customer;
    }
}