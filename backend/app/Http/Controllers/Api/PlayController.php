<?php

namespace App\Http\Controllers\Api;

use App\Actions\Play\RegisterPlay;
use App\Data\Play\RegisterPlayData;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlayResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class PlayController extends Controller
{
    public function store(Game $game, RegisterPlayData $data): JsonResponse
    {
        $result = RegisterPlay::run($game, $data);

        return PlayResource::make($result)->response();
    }
}
