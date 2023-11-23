<?php

namespace App\Entity;

interface FileOwnerInterface
{
    public function getOwnerKey(): ?string;
}
