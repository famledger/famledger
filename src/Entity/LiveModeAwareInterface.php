<?php

namespace App\Entity;

interface LiveModeAwareInterface
{
    public function getLiveMode(): ?bool;

    public function setLiveMode(bool $liveMode): self;
}