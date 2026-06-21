<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SequenceTooShortException extends DomainException
{
    public function __construct(private readonly int $count)
    {
        parent::__construct("Uma sequência precisa de no mínimo 3 cartas para ser aberta (recebeu {$count}).");
    }

    public function context(): array
    {
        return ['count' => $this->count];
    }
}
