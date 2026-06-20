<?php

namespace App\Data\Hand;

use Spatie\LaravelData\Data;

class StoreHandData extends Data
{
    public function __construct(
        public array $cards,
    ) {}

    public static function rules(): array
    {
        return [
            'cards' => ['required', 'array', 'size:13'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
