<?php

namespace App\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

use App\Service\LiveModeContext;

#[AsEventListener(KernelEvents::REQUEST, priority: 10)]
class LiveModeFilterConfigurator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiveModeContext        $liveModeContext
    ) {
    }

    public function onKernelRequest(): void
    {
        // the LiveModeFilter must be enabled for each request
        /** @var LiveModeFilter $filter */
        $filter = $this->em->getFilters()->enable('livemode_filter');
        $filter->setParameter('livemode', $this->liveModeContext->getLiveMode());
    }
}