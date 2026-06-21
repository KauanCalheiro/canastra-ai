<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\SwapSequenceCardData;
use App\Exceptions\Sequence\NothingToSwapException;
use App\Exceptions\Sequence\SwapCardMismatchException;
use App\Models\Card;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardCode;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SwapSequenceCard
{
    use AsAction;

    public Sequence $sequence;

    public Player $player;

    public function handle(Sequence $sequence, int $position, SwapSequenceCardData $data): Sequence
    {
        $this->sequence = $sequence;
        $this->player = Player::where('game_id', $sequence->game_id)->findOrFail($data->playerId);

        Team::ensure($this->player, $sequence->team);

        $oldCard = Card::where('sequence_id', $sequence->id)
            ->where('sequence_position', $position)
            ->firstOrFail();

        if ($oldCard->role !== 'wild') {
            throw new NothingToSwapException($position);
        }

        $startIndex = RankOrder::indexOf($sequence->start_rank);
        $expectedRank = SequenceLegality::expectedRankAt($startIndex, $position);

        if (CardCode::isJoker($data->code)
            || CardCode::rank($data->code) !== $expectedRank
            || CardCode::suit($data->code) !== $sequence->suit) {
            throw new SwapCardMismatchException($data->code, $expectedRank, $sequence->suit);
        }

        return DB::transaction(function () use ($data, $oldCard, $position) {
            $playerIds = Team::playerIds($this->sequence->game_id, $this->sequence->team);
            $claimed = CardPool::claimFromHands([$data->code], $playerIds)->first();

            $oldCard->update([
                'status' => 'hand',
                'player_id' => $this->player->id,
                'sequence_id' => null,
                'sequence_position' => null,
                'role' => null,
            ]);

            $claimed->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $position,
                'role' => 'face',
            ]);

            return $this->sequence->fresh('cards');
        });
    }
}
