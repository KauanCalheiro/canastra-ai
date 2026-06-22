<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DrawnCodeRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('É necessário informar a carta comprada do monte.');
    }
}
