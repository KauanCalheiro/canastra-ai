<?php

namespace App\Actions\Game;

use App\Data\Game\GameDetailData;
use App\Data\Player\PlayerData;
use App\Models\Game;
use App\Models\Player;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGame
{
    use AsAction;

    public Game $game;

    public function handle(Game $game): GameDetailData
    {
        $this->game = $game;
        $this->loadPlayers();

        return $this->toData();
    }

    public function loadPlayers(): void
    {
        $this->game->loadMissing(['players' => fn ($query) => $query->orderBy('seat_index')]);
    }

    public function toData(): GameDetailData
    {
        return new GameDetailData(
            id: $this->game->id,
            decks: $this->game->decks,
            targetScore: $this->game->target_score,
            players: $this->game->players->map(fn (Player $player) => new PlayerData(
                id: $player->id,
                seatIndex: $player->seat_index,
                name: $player->name,
            ))->all(),
        );
    }
}
