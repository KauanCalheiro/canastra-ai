<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateGame;
use App\Data\CreateGameData;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameController extends Controller
{
    public function store(CreateGameData $data, CreateGame $createGame): JsonResponse
    {
        $game = $createGame($data);

        return GameResource::make($game)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
