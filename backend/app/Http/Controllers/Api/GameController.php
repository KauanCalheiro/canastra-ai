<?php

namespace App\Http\Controllers\Api;

use App\Data\CreateGameData;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function store(CreateGameData $data): JsonResponse
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

        return GameResource::make($game)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
