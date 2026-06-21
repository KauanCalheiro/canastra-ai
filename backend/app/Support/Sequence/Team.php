<?php

namespace App\Support\Sequence;

use App\Exceptions\PlayerNotInTeamException;
use App\Models\Player;

class Team
{
    public static function of(Player $player): string
    {
        return $player->seat_index % 2 === 0 ? 'A' : 'B';
    }

    public static function ensure(Player $player, string $team): void
    {
        if (self::of($player) !== $team) {
            throw new PlayerNotInTeamException($player->id, $team);
        }
    }

    /**
     * @return string[]
     */
    public static function playerIds(string $gameId, string $team): array
    {
        return Player::where('game_id', $gameId)
            ->get()
            ->filter(fn (Player $player) => self::of($player) === $team)
            ->pluck('id')
            ->all();
    }
}
