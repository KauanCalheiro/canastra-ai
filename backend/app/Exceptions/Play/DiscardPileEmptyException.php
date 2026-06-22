<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DiscardPileEmptyException extends DomainException
{
    public function __construct()
    {
        parent::__construct('O lixo está vazio — não há carta para pegar.');
    }
}
