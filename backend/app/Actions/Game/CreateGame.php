<?php

namespace App\Actions\Game;

use App\Data\Game\CreateGameData;
use App\Data\Game\GameData;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGame
{
    public function handle(CreateGameData $data): GameData
    {
        $game = DB::transaction(function () use ($data) {
            $game = Game::create([
                'id' => Str::uuid(),
                'decks' => $data->decks,
                'target_score' => $data->targetScore,
            ]);

            foreach ($data->players as $seatIndex => $name) {
                Player::create([
                    'id' => Str::uuid(),
                    'game_id' => $game->id,
                    'seat_index' => $seatIndex,
                    'name' => $name,
                ]);
            }

            return $game;
        });

        return GameData::from($game);
    }
}
