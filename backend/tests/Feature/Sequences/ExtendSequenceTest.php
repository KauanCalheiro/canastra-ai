<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use Illuminate\Support\Str;

function extendMakeHandCard(string $gameId, string $playerId, string $code): Card
{
    return Card::create([
        'id' => (string) Str::uuid(),
        'game_id' => $gameId,
        'code' => $code,
        'status' => 'hand',
        'player_id' => $playerId,
    ]);
}

/**
 * @return array{0: Game, 1: Player[], 2: Sequence}
 */
function makeOpenSequence(array $cards, string $suit, string $startRank, int $playerCount = 2, bool $acesTrinca = false): array
{
    $game = Game::create(['id' => (string) Str::uuid(), 'decks' => 2, 'target_score' => 1000]);
    $names = ['Ana', 'Bruno', 'Carla', 'Diego'];
    $players = [];

    for ($i = 0; $i < $playerCount; $i++) {
        $players[] = Player::create([
            'id' => (string) Str::uuid(),
            'game_id' => $game->id,
            'seat_index' => $i,
            'name' => $names[$i],
        ]);
    }

    foreach ($cards as $code) {
        extendMakeHandCard($game->id, $players[0]->id, $code);
    }

    $response = test()->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $players[0]->id,
        'suit' => $acesTrinca ? null : $suit,
        'startRank' => $acesTrinca ? null : $startRank,
        'acesTrinca' => $acesTrinca,
        'cards' => $cards,
    ]);

    $sequence = Sequence::find($response->json('id'));

    return [$game, $players, $sequence];
}

it('extends a sequence after, continuing the position count and status', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', '5H'], 'H', '3');
    extendMakeHandCard($game->id, $players[0]->id, '6H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['6H'],
        'direction' => 'after',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toBe([
        ['code' => '3H', 'role' => 'face'],
        ['code' => '4H', 'role' => 'face'],
        ['code' => '5H', 'role' => 'face'],
        ['code' => '6H', 'role' => 'face'],
    ]);
});

it('extends a sequence before, shifting existing positions and the start rank', function () {
    [$game, $players, $sequence] = makeOpenSequence(['4H', '5H', '6H'], 'H', '4');
    extendMakeHandCard($game->id, $players[0]->id, '3H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['3H'],
        'direction' => 'before',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toBe([
        ['code' => '3H', 'role' => 'face'],
        ['code' => '4H', 'role' => 'face'],
        ['code' => '5H', 'role' => 'face'],
        ['code' => '6H', 'role' => 'face'],
    ]);
    expect($sequence->fresh()->start_rank)->toBe('3');
});

it('extends an ace trinca, ignoring direction', function () {
    [$game, $players, $sequence] = makeOpenSequence(['AS', 'AH', 'AD'], '', '', acesTrinca: true);
    extendMakeHandCard($game->id, $players[0]->id, 'AC');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['AC'],
        'direction' => 'before',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toHaveCount(4);
    expect(collect($response->json('cards'))->pluck('role')->unique()->all())->toBe(['face']);
});

it('rejects an extension that would exceed the wild joker limit for the whole sequence', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', 'W'], 'H', '3');
    extendMakeHandCard($game->id, $players[0]->id, 'W');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['W'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('max_wild_joker_exceeded');
});

it('rejects an extension that would pass K', function () {
    [$game, $players, $sequence] = makeOpenSequence(['JH', 'QH', 'KH'], 'H', 'J');
    extendMakeHandCard($game->id, $players[0]->id, 'AH');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['AH'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('sequence_rank_out_of_bounds');
});

it('rejects a player from a different team than the sequence', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', '5H'], 'H', '3', playerCount: 2);
    $outsider = $players[1]; // seat 1, team B
    extendMakeHandCard($game->id, $outsider->id, '6H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $outsider->id,
        'cards' => ['6H'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('player_not_in_team');
});
