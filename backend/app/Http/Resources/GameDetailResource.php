<?php

namespace App\Http\Resources;

use App\Data\Game\GameDetailData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GameDetailData */
class GameDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decks' => $this->decks,
            'targetScore' => $this->targetScore,
            'turnIndex' => $this->turnIndex,
            'discardTop' => $this->discardTop,
            'players' => $this->players,
        ];
    }
}
