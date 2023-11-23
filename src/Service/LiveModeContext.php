<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LiveModeContext
{
    const SESSION_KEY = 'live_mode';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string       $defaultLiveMode
    ) {
    }

    private ?bool $liveMode = null;

    public function setLiveMode(?bool $liveMode): void
    {
        $this->liveMode = $liveMode;
        $this->sessionSet($liveMode);
    }

    public function getLiveMode(): bool
    {
        return ($this->liveMode ?? (bool)$this->defaultLiveMode);
    }

    private function sessionGet(): ?int
    {
        return $this->getSession()?->get(self::SESSION_KEY);
    }

    private function sessionSet(?bool $liveMode): void
    {
        $this->getSession()?->set(self::SESSION_KEY, $liveMode);
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getCurrentRequest()?->getSession();
        } catch (SessionNotFoundException $e) {
            return null;
        }
    }
}