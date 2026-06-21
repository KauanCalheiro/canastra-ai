<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class ExtendSequenceData extends Data
{
    public function __construct(
        public string $playerId,
        public array $cards,
        public string $direction,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'cards' => ['required', 'array', 'min:1'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'direction' => ['required', 'in:before,after'],
        ];
    }
}
