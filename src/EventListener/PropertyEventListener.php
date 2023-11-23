<?php

namespace App\EventListener;

use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use App\Entity\Property;
use App\Event\Invoice\BaseInvoiceEvent;
use App\Event\Invoice\InvoiceCompletedEvent;
use App\Event\Invoice\InvoiceDetailsFetchedEvent;
use App\Repository\PropertyRepository;

#[AsEventListener(event: InvoiceCompletedEvent::class, method: 'onInvoiceCompleted')]
#[AsEventListener(event: InvoiceDetailsFetchedEvent::class, method: 'onInvoiceCompleted')]
class PropertyEventListener
{
    private array  $propertyCache = [];
    private ?array $matchStrings  = null;

    public function __construct(
        private readonly PropertyRepository $propertyRepo
    ) {
    }

    /**
     * @throws Exception
     */
    public function onInvoiceCompleted(BaseInvoiceEvent $event): void
    {
        $invoice = $event->getInvoice();
        if (null !== $property = $this->matchProperty($invoice->getDescription() ?? '')) {
            $invoice->setProperty($property);
        }
    }

    private function matchProperty(string $description): ?Property
    {
        $matchStrings = $this->getMatchStrings();
        $matchString  = substr($description, 0, strpos($description, ','));
        if (null === $slug = $matchStrings[$matchString] ?? null) {
            return null;
        }

        if (isset($this->propertyCache[$slug])) {
            return $this->propertyCache[$slug];
        }

        $property = $this->propertyRepo->findOneBy(['slug' => $slug]);
        if (null === $property) {
            return null;
        }

        $this->propertyCache[$slug] = $property;

        return $property;
    }

    private function getMatchStrings(): array
    {
        if (null === $this->matchStrings) {
            $this->matchStrings = [];
            foreach ($this->propertyRepo->findAll() as $property) {
                $this->matchStrings[$property->getMatchString()] = $property->getSlug();
            }
        }

        return $this->matchStrings;
    }
}