<?php

namespace App\Actions\Game;

use App\Data\Game\CreateGameData;
use App\Data\Game\GameData;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateGame
{
    use AsAction;

    protected CreateGameData $data;

    public ?Game $game = null;

    /** @var Player[] */
    public array $players = [];

    public function handle(CreateGameData $data): GameData
    {
        $this->data = $data;

        DB::transaction(function () {
            $this->createGame();
            $this->batchCreatePlayer();
            $this->createDeck();
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

    public function createDeck(): void
    {
        $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'];
        $suits = ['S', 'H', 'C', 'D'];

        $cards = [];

        for ($deck = 0; $deck < $this->data->decks; $deck++) {
            foreach ($suits as $suit) {
                foreach ($ranks as $rank) {
                    $cards[] = [
                        'id' => (string) Str::uuid(),
                        'game_id' => $this->game->id,
                        'code' => $rank.$suit,
                        'status' => 'deck',
                        'player_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            for ($joker = 0; $joker < 2; $joker++) {
                $cards[] = [
                    'id' => (string) Str::uuid(),
                    'game_id' => $this->game->id,
                    'code' => 'W',
                    'status' => 'deck',
                    'player_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Card::insert($cards);
    }
}
