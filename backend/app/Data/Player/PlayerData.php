<?php

namespace App\Data\Player;

use Spatie\LaravelData\Data;

class PlayerData extends Data
{
    public function __construct(
        public string $id,
        public int $seatIndex,
        public string $name,
    ) {}
}
