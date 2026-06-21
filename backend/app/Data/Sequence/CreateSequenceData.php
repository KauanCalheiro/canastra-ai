<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class CreateSequenceData extends Data
{
    public function __construct(
        public string $playerId,
        public ?string $suit,
        public ?string $startRank,
        public bool $acesTrinca,
        public array $cards,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'suit' => ['nullable', 'string', 'in:S,H,C,D'],
            'startRank' => ['nullable', 'string', 'in:A,2,3,4,5,6,7,8,9,T,J,Q,K'],
            'acesTrinca' => ['boolean'],
            'cards' => ['required', 'array'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
