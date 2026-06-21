<?php

namespace App\Support\Cards;

class RankOrder
{
    /** @var string[] */
    public const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'];

    public static function indexOf(string $rank): int
    {
        $index = array_search($rank, self::RANKS, true);

        if ($index === false) {
            throw new \InvalidArgumentException("Rank inválido: {$rank}");
        }

        return $index;
    }

    public static function rankAt(int $index): ?string
    {
        return self::RANKS[$index] ?? null;
    }
}
