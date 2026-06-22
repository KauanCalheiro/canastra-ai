<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class LoweredCountExceedsHandException extends DomainException
{
    public function __construct(
        private readonly int $loweredCount,
        private readonly int $available,
    ) {
        parent::__construct("Não é possível baixar {$loweredCount} cartas — a mão só tem {$available}.");
    }

    public function context(): array
    {
        return ['loweredCount' => $this->loweredCount, 'available' => $this->available];
    }
}
