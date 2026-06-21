<?php

namespace App\Exceptions;

class InsufficientCardsInPoolException extends DomainException
{
    public function __construct(
        private readonly string $cardCode,
        private readonly int $needed,
        private readonly int $available,
    ) {
        parent::__construct("Não há cópias suficientes de \"{$cardCode}\" disponíveis (precisa de {$needed}, há {$available}).");
    }

    public function context(): array
    {
        return [
            'code' => $this->cardCode,
            'needed' => $this->needed,
            'available' => $this->available,
        ];
    }
}
