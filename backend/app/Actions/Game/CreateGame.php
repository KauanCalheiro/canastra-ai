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
            $game = $this->createGame($data);
            $this->batchCreatePlayer($game, $data->players);

            return $game;
        });

        return GameData::from($game);
    }

    public function createGame(CreateGameData $data): Game
    {
        return Game::create([
            'id' => Str::uuid(),
            'decks' => $data->decks,
            'target_score' => $data->targetScore,
        ]);
    }

    public function batchCreatePlayer(Game $game, array $players): void
    {
        foreach ($players as $seatIndex => $name) {
            $this->createPlayer($game, $seatIndex, $name);
        }
    }

    public function createPlayer(Game $game, int $seatIndex, string $name): Player
    {
        return Player::create([
            'id' => Str::uuid(),
            'game_id' => $game->id,
            'seat_index' => $seatIndex,
            'name' => $name,
        ]);
    }
}
