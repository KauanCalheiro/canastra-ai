<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\CreateSequenceData;
use App\Exceptions\Sequence\SequenceTooShortException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSequence
{
    use AsAction;

    public Game $game;

    public Player $player;

    public string $team;

    public Sequence $sequence;

    public function handle(Game $game, CreateSequenceData $data): Sequence
    {
        $this->game = $game;
        $this->player = Player::where('game_id', $game->id)->findOrFail($data->playerId);
        $this->team = Team::of($this->player);

        if (count($data->cards) < 3) {
            throw new SequenceTooShortException(count($data->cards));
        }

        $roledCards = $data->acesTrinca
            ? $this->resolveAceTrincaRoles($data->cards)
            : $this->resolveNormalRoles($data->cards, $data->suit, $data->startRank);

        SequenceLegality::validateWildcardLimits($roledCards);

        return DB::transaction(function () use ($data, $roledCards) {
            $playerIds = Team::playerIds($this->game->id, $this->team);
            $claimed = CardPool::claimFromHands($data->cards, $playerIds);

            $this->sequence = Sequence::create([
                'id' => (string) Str::uuid(),
                'game_id' => $this->game->id,
                'team' => $this->team,
                'suit' => $data->acesTrinca ? null : $data->suit,
                'is_ace_trinca' => $data->acesTrinca,
                'start_rank' => $data->acesTrinca ? null : $data->startRank,
            ]);

            $this->assignPositions($claimed, $roledCards);

            return $this->sequence;
        });
    }

    /**
     * @param  string[]  $codes
     * @return array<int, array{code: string, role: string}>
     */
    public function resolveAceTrincaRoles(array $codes): array
    {
        return array_map(
            fn (string $code) => ['code' => $code, 'role' => SequenceLegality::resolveAceTrincaRole($code)],
            $codes
        );
    }

    /**
     * @param  string[]  $codes
     * @return array<int, array{code: string, role: string}>
     */
    public function resolveNormalRoles(array $codes, string $suit, string $startRank): array
    {
        $startIndex = RankOrder::indexOf($startRank);
        $roled = [];

        foreach ($codes as $offset => $code) {
            $expectedRank = SequenceLegality::expectedRankAt($startIndex, $offset);
            $roled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $suit)];
        }

        return $roled;
    }

    /**
     * @param  Collection<int, Card>  $claimed
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public function assignPositions(Collection $claimed, array $roledCards): void
    {
        $byCode = $claimed->groupBy('code')->map(fn ($group) => $group->values());
        $pointers = [];

        foreach ($roledCards as $position => $entry) {
            $code = $entry['code'];
            $pointer = $pointers[$code] ?? 0;
            $pointers[$code] = $pointer + 1;

            $byCode[$code][$pointer]->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $position,
                'role' => $entry['role'],
            ]);
        }
    }
}
