<?php

namespace App\Actions\Play;

use App\Data\Play\RegisterPlayData;
use App\Exceptions\InsufficientCardsInPoolException;
use App\Exceptions\Play\DiscardPileEmptyException;
use App\Exceptions\Play\DiscardRequiredException;
use App\Exceptions\Play\DrawnCodeRequiredException;
use App\Exceptions\Play\LoweredCountExceedsHandException;
use App\Exceptions\Play\NotPlayersTurnException;
use App\Exceptions\Play\NothingToDiscardException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Play;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterPlay
{
    use AsAction;

    public Game $game;

    public Player $player;

    public bool $isTracked;

    /**
     * @return array<string, mixed>
     */
    public function handle(Game $game, RegisterPlayData $data): array
    {
        $this->game = $game;

        $players = $game->players()->orderBy('seat_index')->get();
        $currentPlayer = $players[$game->turn_index % $players->count()];

        if ($currentPlayer->id !== $data->playerId) {
            throw new NotPlayersTurnException($data->playerId, $currentPlayer->id);
        }

        $this->player = $currentPlayer;
        $this->isTracked = $this->player->seat_index === 0;

        return DB::transaction(function () use ($data, $players) {
            $this->handleDraw($data);

            $remaining = $this->player->hand_count + 1 - $data->loweredCount;

            if ($remaining < 0) {
                throw new LoweredCountExceedsHandException($data->loweredCount, $this->player->hand_count + 1);
            }

            $this->handleDiscard($data, $remaining);

            $turnIndexBefore = $this->game->turn_index;

            Play::create([
                'id' => (string) Str::uuid(),
                'game_id' => $this->game->id,
                'player_id' => $this->player->id,
                'turn_index' => $turnIndexBefore,
                'drew_from' => $data->drewFrom,
                'discarded_code' => $data->discardedCode,
                'lowered_count' => $data->loweredCount,
            ]);

            $this->game->update(['turn_index' => $turnIndexBefore + 1]);

            $nextPlayer = $players[($turnIndexBefore + 1) % $players->count()];

            return [
                'playerId' => $this->player->id,
                'turnIndex' => $turnIndexBefore,
                'drewFrom' => $data->drewFrom,
                'discardedCode' => $data->discardedCode,
                'loweredCount' => $data->loweredCount,
                'handCountAfter' => $this->player->hand_count,
                'nextPlayerId' => $nextPlayer->id,
            ];
        });
    }

    public function handleDraw(RegisterPlayData $data): void
    {
        if ($data->drewFrom === 'monte') {
            if (! $this->isTracked) {
                return;
            }

            if ($data->drawnCode === null) {
                throw new DrawnCodeRequiredException();
            }

            $card = Card::where('game_id', $this->game->id)
                ->where('code', $data->drawnCode)
                ->where('status', 'deck')
                ->lockForUpdate()
                ->first();

            if ($card === null) {
                throw new InsufficientCardsInPoolException($data->drawnCode, 1, 0);
            }

            $card->update(['status' => 'hand', 'player_id' => $this->player->id]);

            return;
        }

        $top = Card::where('game_id', $this->game->id)
            ->where('status', 'discard')
            ->orderByDesc('discard_position')
            ->lockForUpdate()
            ->first();

        if ($top === null) {
            throw new DiscardPileEmptyException();
        }

        if ($this->isTracked) {
            $top->update(['status' => 'hand', 'player_id' => $this->player->id, 'discard_position' => null]);
        } else {
            $top->update(['status' => 'deck', 'player_id' => null, 'discard_position' => null]);
        }
    }

    public function handleDiscard(RegisterPlayData $data, int $remaining): void
    {
        if ($remaining === 0) {
            if ($data->discardedCode !== null) {
                throw new NothingToDiscardException();
            }

            $this->player->update(['hand_count' => 0]);

            return;
        }

        if ($data->discardedCode === null) {
            throw new DiscardRequiredException();
        }

        if ($this->isTracked) {
            $card = Card::where('game_id', $this->game->id)
                ->where('code', $data->discardedCode)
                ->where('status', 'hand')
                ->where('player_id', $this->player->id)
                ->lockForUpdate()
                ->first();
        } else {
            $card = Card::where('game_id', $this->game->id)
                ->where('code', $data->discardedCode)
                ->where('status', 'deck')
                ->lockForUpdate()
                ->first();
        }

        if ($card === null) {
            throw new InsufficientCardsInPoolException($data->discardedCode, 1, 0);
        }

        $nextPosition = (Card::where('game_id', $this->game->id)->where('status', 'discard')->max('discard_position') ?? 0) + 1;

        $card->update([
            'status' => 'discard',
            'player_id' => $this->player->id,
            'discard_position' => $nextPosition,
        ]);

        $this->player->update(['hand_count' => $remaining - 1]);
    }
}
