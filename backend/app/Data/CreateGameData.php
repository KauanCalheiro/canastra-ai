<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateGameData extends Data
{
    public function __construct(
        #[In(1, 2, 3)]
        public int $decks,
        #[Min(100)]
        public int $targetScore,
        #[ArrayType]
        public array $players,
    ) {}

    public static function rules(): array
    {
        return [
            'players' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (count($value) !== 2 && count($value) !== 4) {
                        $fail('É necessário informar 2 ou 4 jogadores.');
                    }
                },
            ],
            'players.*' => ['string'],
        ];
    }
}
