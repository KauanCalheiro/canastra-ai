<?php

namespace App\Support\Cards;

use App\Exceptions\InsufficientCardsInPoolException;
use App\Models\Card;
use Illuminate\Support\Collection;

class CardPool
{
    /**
     * @param  string[]  $codes
     * @param  string[]  $playerIds
     * @return Collection<int, Card>
     */
    public static function claimFromHands(array $codes, array $playerIds): Collection
    {
        $claimed = collect();

        foreach (array_count_values($codes) as $code => $quantity) {
            $rows = Card::where('code', $code)
                ->where('status', 'hand')
                ->whereIn('player_id', $playerIds)
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($rows->count() < $quantity) {
                throw new InsufficientCardsInPoolException($code, $quantity, $rows->count());
            }

            $claimed = $claimed->merge($rows);
        }

        return $claimed;
    }
}
