<?php

namespace App\Actions\Hand;

use App\Data\Hand\StoreHandData;
use App\Models\Card;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePlayerHand
{
    use AsAction;

    /**
     * @return string[]
     */
    public function handle(Player $player, StoreHandData $data): array
    {
        DB::transaction(function () use ($player, $data) {
            $this->releaseCurrentHand($player);
            $this->claimCards($player, $data->cards);
        });

        return $data->cards;
    }

    public function releaseCurrentHand(Player $player): void
    {
        Card::where('player_id', $player->id)
            ->where('status', 'hand')
            ->update(['status' => 'deck', 'player_id' => null]);
    }

    /**
     * @param  string[]  $codes
     */
    public function claimCards(Player $player, array $codes): void
    {
        foreach (array_count_values($codes) as $code => $quantity) {
            $rows = Card::where('game_id', $player->game_id)
                ->where('code', $code)
                ->where('status', 'deck')
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($rows->count() < $quantity) {
                throw ValidationException::withMessages([
                    'cards' => "Não há cópias suficientes de \"{$code}\" disponíveis no baralho desta partida.",
                ]);
            }

            Card::whereIn('id', $rows->pluck('id'))
                ->update(['status' => 'hand', 'player_id' => $player->id]);
        }
    }
}
