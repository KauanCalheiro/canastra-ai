<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SequenceRankOutOfBoundsException extends DomainException
{
    public function __construct(private readonly int $index)
    {
        parent::__construct("A sequência ultrapassaria os limites de A a K (índice calculado: {$index}).");
    }

    public function context(): array
    {
        return ['index' => $this->index];
    }
}
