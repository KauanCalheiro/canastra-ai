<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DiscardRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('É necessário descartar uma carta nesta jogada.');
    }
}
