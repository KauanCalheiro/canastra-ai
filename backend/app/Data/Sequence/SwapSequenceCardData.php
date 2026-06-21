<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class SwapSequenceCardData extends Data
{
    public function __construct(
        public string $playerId,
        public string $code,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
