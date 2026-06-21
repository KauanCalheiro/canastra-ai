<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use Illuminate\Support\Str;

function swapMakeHandCard(string $gameId, string $playerId, string $code): Card
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
function makeSequenceForSwap(array $cards, string $suit, string $startRank): array
{
    $game = Game::create(['id' => (string) Str::uuid(), 'decks' => 2, 'target_score' => 1000]);
    $players = [
        Player::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']),
        Player::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']),
    ];

    foreach ($cards as $code) {
        swapMakeHandCard($game->id, $players[0]->id, $code);
    }

    $response = test()->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $players[0]->id,
        'suit' => $suit,
        'startRank' => $startRank,
        'acesTrinca' => false,
        'cards' => $cards,
    ]);

    return [$game, $players, Sequence::find($response->json('id'))];
}

it('swaps a wild joker for the matching real card, returning the joker to hand', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '5H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5H',
    ]);

    $response->assertOk();
    expect($response->json('cards.2'))->toBe(['code' => '5H', 'role' => 'face']);

    $freed = Card::where('code', 'W')->where('game_id', $game->id)->first();
    expect($freed->status)->toBe('hand');
    expect($freed->player_id)->toBe($players[0]->id);
    expect($freed->sequence_id)->toBeNull();
});

it('swaps a wild two for the matching real card, turning a dirty canastra clean', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', '5H', '6H', '7H', '8H', '2D'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '9H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/6/swap", [
        'playerId' => $players[0]->id,
        'code' => '9H',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('clean');
});

it('rejects swapping a position that already holds a face card', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', '5H'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '4H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/1/swap", [
        'playerId' => $players[0]->id,
        'code' => '4H',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('nothing_to_swap');
});

it('rejects a swap code that does not match the expected rank and suit', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '5D');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5D',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('swap_card_mismatch');
});

it('rejects a swap when the code is not available in the teams hands', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    // 5H is never given to any player's hand

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5H',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('insufficient_cards_in_pool');
});
