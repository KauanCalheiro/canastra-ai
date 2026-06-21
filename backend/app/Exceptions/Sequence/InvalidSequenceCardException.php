<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class InvalidSequenceCardException extends DomainException
{
    public function __construct(
        private readonly string $cardCode,
        private readonly string $expectedRank,
        private readonly string $suit,
    ) {
        parent::__construct("A carta \"{$cardCode}\" não combina com a posição esperada ({$expectedRank} de {$suit}) e não é um curinga válido.");
    }

    public function context(): array
    {
        return ['code' => $this->cardCode, 'expectedRank' => $this->expectedRank, 'suit' => $this->suit];
    }
}
