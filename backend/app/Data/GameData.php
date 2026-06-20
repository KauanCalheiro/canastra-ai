<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class GameData extends Data
{
    public function __construct(
        public string $id,
    ) {}
}
