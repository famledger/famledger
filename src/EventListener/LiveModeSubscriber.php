<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use App\Service\LiveModeContext;

#[AsEventListener(event: RequestEvent::class, method: 'onKernelRequest')]
class LiveModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack    $requestStack,
        private readonly LiveModeContext $liveModeContext
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (null !== $liveMode = $this->requestStack->getCurrentRequest()->getSession()->get('liveMode')) {
            $this->liveModeContext->setLiveMode($liveMode);
        }
    }
}