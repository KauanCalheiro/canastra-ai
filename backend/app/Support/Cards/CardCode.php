<?php

namespace App\Support\Cards;

class CardCode
{
    public static function isJoker(string $code): bool
    {
        return $code === 'W';
    }

    public static function rank(string $code): string
    {
        return self::isJoker($code) ? 'W' : substr($code, 0, -1);
    }

    public static function suit(string $code): ?string
    {
        return self::isJoker($code) ? null : substr($code, -1);
    }
}
