<?php

namespace App\Data\Play;

use Spatie\LaravelData\Data;

class RegisterPlayData extends Data
{
    public function __construct(
        public string $playerId,
        public string $drewFrom,
        public ?string $drawnCode,
        public ?string $discardedCode,
        public int $loweredCount = 0,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'drewFrom' => ['required', 'in:monte,lixo'],
            'drawnCode' => ['nullable', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'discardedCode' => ['nullable', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'loweredCount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
