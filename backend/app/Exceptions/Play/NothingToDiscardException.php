<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class NothingToDiscardException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A mão ficaria vazia — não há carta para descartar.');
    }
}
