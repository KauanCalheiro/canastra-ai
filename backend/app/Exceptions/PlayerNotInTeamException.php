<?php

namespace App\Exceptions;

class PlayerNotInTeamException extends DomainException
{
    public function __construct(
        private readonly string $playerId,
        private readonly string $team,
    ) {
        parent::__construct("O jogador \"{$playerId}\" não pertence à dupla \"{$team}\".");
    }

    public function context(): array
    {
        return ['playerId' => $this->playerId, 'team' => $this->team];
    }
}
