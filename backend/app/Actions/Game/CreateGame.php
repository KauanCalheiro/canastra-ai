<?php

namespace App\Actions\Game;

use App\Data\Game\CreateGameData;
use App\Data\Game\GameData;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateGame
{
    use AsAction;

    public ?Game $game = null;

    /** @var Player[] */
    public array $players = [];

    public function __construct(
        protected CreateGameData $data,
    ) {}

    public function handle(): GameData
    {
        DB::transaction(function () {
            $this->createGame();
            $this->batchCreatePlayer();
        });

        return GameData::from($this->game);
    }

    public function createGame(): Game
    {
        $this->game = Game::create([
            'id' => Str::uuid(),
            'decks' => $this->data->decks,
            'target_score' => $this->data->targetScore,
        ]);

        return $this->game;
    }

    public function batchCreatePlayer(): void
    {
        foreach ($this->data->players as $seatIndex => $name) {
            $this->createPlayer($seatIndex, $name);
        }
    }

    public function createPlayer(int $seatIndex, string $name): Player
    {
        $player = Player::create([
            'id' => Str::uuid(),
            'game_id' => $this->game->id,
            'seat_index' => $seatIndex,
            'name' => $name,
        ]);

        $this->players[] = $player;

        return $player;
    }
}
