<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class NothingToSwapException extends DomainException
{
    public function __construct(private readonly int $position)
    {
        parent::__construct("A posição {$position} já tem uma carta natural — não há curinga para trocar.");
    }

    public function context(): array
    {
        return ['position' => $this->position];
    }
}
