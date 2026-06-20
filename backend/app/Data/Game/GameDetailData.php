<?php

namespace App\Data\Game;

use App\Data\Player\PlayerData;
use Spatie\LaravelData\Data;

class GameDetailData extends Data
{
    public function __construct(
        public string $id,
        public int $decks,
        public int $targetScore,
        /** @var PlayerData[] */
        public array $players,
    ) {}
}
