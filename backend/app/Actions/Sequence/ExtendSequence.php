<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\ExtendSequenceData;
use App\Models\Card;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtendSequence
{
    use AsAction;

    public Sequence $sequence;

    public Player $player;

    public function handle(Sequence $sequence, ExtendSequenceData $data): Sequence
    {
        $this->sequence = $sequence;
        $this->player = Player::where('game_id', $sequence->game_id)->findOrFail($data->playerId);

        Team::ensure($this->player, $sequence->team);

        $existingCards = $sequence->cards;
        $existingRoled = $existingCards->map(fn (Card $card) => ['code' => $card->code, 'role' => $card->role])->all();

        if ($sequence->is_ace_trinca) {
            $newRoled = array_map(
                fn (string $code) => ['code' => $code, 'role' => SequenceLegality::resolveAceTrincaRole($code)],
                $data->cards
            );
            $combined = array_merge($existingRoled, $newRoled);
            $prepend = false;
            $newStartIndex = null;
        } elseif ($data->direction === 'after') {
            $startIndex = RankOrder::indexOf($sequence->start_rank);
            $newRoled = [];
            foreach ($data->cards as $i => $code) {
                $expectedRank = SequenceLegality::expectedRankAt($startIndex, count($existingCards) + $i);
                $newRoled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $sequence->suit)];
            }
            $combined = array_merge($existingRoled, $newRoled);
            $prepend = false;
            $newStartIndex = $startIndex;
        } else {
            $startIndex = RankOrder::indexOf($sequence->start_rank);
            $newStartIndex = $startIndex - count($data->cards);
            $newRoled = [];
            foreach ($data->cards as $i => $code) {
                $expectedRank = SequenceLegality::expectedRankAt($newStartIndex, $i);
                $newRoled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $sequence->suit)];
            }
            $combined = array_merge($newRoled, $existingRoled);
            $prepend = true;
        }

        SequenceLegality::validateWildcardLimits($combined);

        return DB::transaction(function () use ($data, $newRoled, $prepend, $newStartIndex, $existingCards) {
            $playerIds = Team::playerIds($this->sequence->game_id, $this->sequence->team);
            $claimed = CardPool::claimFromHands($data->cards, $playerIds);

            if ($prepend) {
                foreach ($existingCards as $card) {
                    $card->update(['sequence_position' => $card->sequence_position + count($data->cards)]);
                }
                $this->sequence->update(['start_rank' => RankOrder::rankAt($newStartIndex)]);
                $offset = 0;
            } else {
                $offset = count($existingCards);
            }

            $this->assignPositions($claimed, $newRoled, $offset);

            return $this->sequence->fresh('cards');
        });
    }

    /**
     * @param  Collection<int, Card>  $claimed
     * @param  array<int, array{code: string, role: string}>  $newRoled
     */
    public function assignPositions(Collection $claimed, array $newRoled, int $offset): void
    {
        $byCode = $claimed->groupBy('code')->map(fn ($group) => $group->values());
        $pointers = [];

        foreach ($newRoled as $i => $entry) {
            $code = $entry['code'];
            $pointer = $pointers[$code] ?? 0;
            $pointers[$code] = $pointer + 1;

            $byCode[$code][$pointer]->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $offset + $i,
                'role' => $entry['role'],
            ]);
        }
    }
}
