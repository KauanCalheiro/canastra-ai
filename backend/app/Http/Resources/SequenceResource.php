<?php

namespace App\Http\Resources;

use App\Support\Sequence\SequenceLegality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sequence */
class SequenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cards = $this->cards->map(fn ($card) => ['code' => $card->code, 'role' => $card->role])->all();

        return [
            'id' => $this->id,
            'team' => $this->team,
            'status' => SequenceLegality::computeStatus($cards),
            'cards' => $cards,
        ];
    }
}
