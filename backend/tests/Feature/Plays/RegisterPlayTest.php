<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;

function playGiveHandCard(string $gameId, string $playerId, string $code): Card
{
    $card = Card::where('game_id', $gameId)->where('code', $code)->where('status', 'deck')->first();
    $card->update(['status' => 'hand', 'player_id' => $playerId]);

    return $card->fresh();
}

/**
 * @return array{0: Game, 1: Player[]}
 */
function playMakeGameWithPlayers(int $playerCount = 2, int $decks = 2): array
{
    $names = ['Ana', 'Bruno', 'Carla', 'Diego'];
    $response = test()->postJson('/api/games', [
        'decks' => $decks,
        'targetScore' => 1000,
        'players' => array_slice($names, 0, $playerCount),
    ]);

    $gameId = $response->json('id');
    $game = Game::find($gameId);
    $players = Player::where('game_id', $gameId)->orderBy('seat_index')->get()->all();

    return [$game, $players];
}

it('registers a tracked monte draw with a discard and advances the turn', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    playGiveHandCard($game->id, $player0->id, '3H');

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'discardedCode' => '3H',
        'loweredCount' => 0,
    ]);

    $response->assertOk();
    expect($response->json('nextPlayerId'))->toBe($players[1]->id);
    expect($response->json('handCountAfter'))->toBe(13);
    expect(Card::where('game_id', $game->id)->where('code', '5H')->first()->status)->toBe('hand');
    expect(Card::where('game_id', $game->id)->where('code', '3H')->first()->status)->toBe('discard');
    expect($game->fresh()->turn_index)->toBe(1);
    expect($player0->fresh()->hand_count)->toBe(13);
});

it('registers an untracked monte draw without a code, revealing the discard from the deck pool', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $game->update(['turn_index' => 1]);
    $player1 = $players[1];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player1->id,
        'drewFrom' => 'monte',
        'discardedCode' => '9C',
        'loweredCount' => 0,
    ]);

    $response->assertOk();
    expect(Card::where('game_id', $game->id)->where('code', '9C')->first()->status)->toBe('discard');
    expect($player1->fresh()->hand_count)->toBe(13);
    expect($game->fresh()->turn_index)->toBe(2);
});

it('lets an untracked player pick up the discard pile, returning it to the anonymous deck pool', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    playGiveHandCard($game->id, $player0->id, '3H');

    $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '4H',
        'discardedCode' => '3H',
        'loweredCount' => 0,
    ])->assertOk();

    $player1 = $players[1];
    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player1->id,
        'drewFrom' => 'lixo',
        'discardedCode' => '6H',
        'loweredCount' => 0,
    ]);

    $response->assertOk();
    $card = Card::where('game_id', $game->id)->where('code', '3H')->first();
    expect($card->status)->toBe('deck');
    expect($card->player_id)->toBeNull();
});

it('lets a tracked player keep a picked-up discard in their identified hand', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    $player1 = $players[1];

    // player0 discards 7C, putting it on top of the discard pile
    playGiveHandCard($game->id, $player0->id, '7C');
    $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '4H',
        'discardedCode' => '7C',
        'loweredCount' => 0,
    ])->assertOk();

    // player1 (untracked) draws from monte and discards something, passing the turn back
    $play1Result = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player1->id,
        'drewFrom' => 'monte',
        'discardedCode' => '2C',
        'loweredCount' => 0,
    ]);
    $play1Result->assertOk();

    // player0's turn again: picks up the lixo (top = 2C) into their identified hand,
    // then immediately discards that same card again from that hand
    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'lixo',
        'discardedCode' => '2C',
        'loweredCount' => 0,
    ]);

    $response->assertOk();
    $card = Card::where('game_id', $game->id)->where('code', '2C')->first();
    expect($card->status)->toBe('discard');
    expect($card->player_id)->toBe($player0->id);
});

it('allows batting with no discard when the lowered count empties the hand', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    $player0->update(['hand_count' => 0]);

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'loweredCount' => 1,
    ]);

    $response->assertOk();
    expect($response->json('handCountAfter'))->toBe(0);
    expect($player0->fresh()->hand_count)->toBe(0);
});

it('rejects a discard when the lowered count would empty the hand', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    $player0->update(['hand_count' => 0]);

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'discardedCode' => '6H',
        'loweredCount' => 1,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('nothing_to_discard');
});

it('rejects a missing discard when cards remain in hand', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'loweredCount' => 0,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('discard_required');
});

it('rejects a lowered count greater than the hand size', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'loweredCount' => 99,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('lowered_count_exceeds_hand');
});

it('rejects a play from a player whose turn it is not', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player1 = $players[1];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player1->id,
        'drewFrom' => 'monte',
        'discardedCode' => '9C',
        'loweredCount' => 0,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('not_players_turn');
});

it('rejects a tracked monte draw without a drawn code', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'discardedCode' => '9C',
        'loweredCount' => 0,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('drawn_code_required');
});

it('rejects picking up the discard pile when it is empty', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'lixo',
        'discardedCode' => '9C',
        'loweredCount' => 0,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('discard_pile_empty');
});

it('rejects a tracked draw of a code that is not available in the deck', function () {
    [$game, $players] = playMakeGameWithPlayers(2, decks: 1);
    $player0 = $players[0];
    $player1 = $players[1];
    playGiveHandCard($game->id, $player1->id, '5H');

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'monte',
        'drawnCode' => '5H',
        'discardedCode' => '9C',
        'loweredCount' => 0,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('insufficient_cards_in_pool');
});
