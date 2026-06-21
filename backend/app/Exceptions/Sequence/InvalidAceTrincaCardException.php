<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class InvalidAceTrincaCardException extends DomainException
{
    public function __construct(private readonly string $cardCode)
    {
        parent::__construct("A carta \"{$cardCode}\" não é válida numa trinca de ases (precisa ser um Ás, sem curingas).");
    }

    public function context(): array
    {
        return ['code' => $this->cardCode];
    }
}
