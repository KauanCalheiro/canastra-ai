<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class MaxWildTwoExceededException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Uma sequência só pode ter 1 coringuinha (2) usado como curinga.');
    }
}
