<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SwapCardMismatchException extends DomainException
{
    public function __construct(
        private readonly string $cardCode,
        private readonly string $expectedRank,
        private readonly string $suit,
    ) {
        parent::__construct("A carta \"{$cardCode}\" não corresponde à posição esperada ({$expectedRank} de {$suit}).");
    }

    public function context(): array
    {
        return ['code' => $this->cardCode, 'expectedRank' => $this->expectedRank, 'suit' => $this->suit];
    }
}
