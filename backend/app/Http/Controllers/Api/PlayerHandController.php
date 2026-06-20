<?php

namespace App\Http\Controllers\Api;

use App\Actions\Hand\StorePlayerHand;
use App\Data\Hand\StoreHandData;
use App\Http\Controllers\Controller;
use App\Http\Resources\HandResource;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerHandController extends Controller
{
    public function store(Player $player, StoreHandData $data): JsonResponse
    {
        $cards = StorePlayerHand::run($player, $data);

        return HandResource::make(['cards' => $cards])->response();
    }
}
