<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class WildcardCoexistenceException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Coringão e coringuinha-curinga não podem coexistir na mesma sequência.');
    }
}
