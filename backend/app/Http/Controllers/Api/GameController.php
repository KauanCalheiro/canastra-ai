<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGameRequest;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function store(StoreGameRequest $request): JsonResponse
    {
        $gameId = DB::transaction(function () use ($request) {
            $game = Game::create([
                'id' => Str::uuid(),
                'decks' => $request->integer('decks'),
                'target_score' => $request->integer('targetScore'),
            ]);

            foreach ($request->input('players') as $seatIndex => $name) {
                Player::create([
                    'id' => Str::uuid(),
                    'game_id' => $game->id,
                    'seat_index' => $seatIndex,
                    'name' => $name,
                ]);
            }

            return $game->id;
        });

        return response()->json(['id' => $gameId], 201);
    }
}
