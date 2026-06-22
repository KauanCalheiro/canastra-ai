<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class NotPlayersTurnException extends DomainException
{
    public function __construct(
        private readonly string $providedPlayerId,
        private readonly string $expectedPlayerId,
    ) {
        parent::__construct("Não é a vez do jogador \"{$providedPlayerId}\" — é a vez de \"{$expectedPlayerId}\".");
    }

    public function context(): array
    {
        return ['providedPlayerId' => $this->providedPlayerId, 'expectedPlayerId' => $this->expectedPlayerId];
    }
}
