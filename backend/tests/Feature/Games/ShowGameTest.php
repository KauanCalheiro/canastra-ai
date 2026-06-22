<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Str;

it('returns the game with decks, target score and players ordered by seat index', function () {
    $game = Game::create([
        'id' => 'game-1',
        'decks' => 2,
        'target_score' => 1500,
    ]);

    Player::create(['id' => 'player-b', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']);
    Player::create(['id' => 'player-a', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);

    $response = $this->getJson("/api/games/{$game->id}");

    $response->assertOk();
    $response->assertJson([
        'id' => $game->id,
        'decks' => 2,
        'targetScore' => 1500,
    ]);

    $players = $response->json('players');
    expect($players)->toHaveCount(2);
    expect($players[0]['seatIndex'])->toBe(0);
    expect($players[0]['name'])->toBe('Ana');
    expect($players[1]['seatIndex'])->toBe(1);
    expect($players[1]['name'])->toBe('Bruno');
});

it('returns 404 for a game that does not exist', function () {
    $response = $this->getJson('/api/games/does-not-exist');

    $response->assertNotFound();
});

it('returns turnIndex, handCount per player, and the top of the discard pile', function () {
    $game = Game::create(['id' => 'game-turn', 'decks' => 1, 'target_score' => 1500, 'turn_index' => 2]);
    Player::create(['id' => 'player-turn-a', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana', 'hand_count' => 11]);
    Player::create(['id' => 'player-turn-b', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno', 'hand_count' => 13]);
    Card::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'code' => '7H', 'status' => 'discard', 'discard_position' => 1]);
    Card::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'code' => '8H', 'status' => 'discard', 'discard_position' => 2]);

    $response = $this->getJson("/api/games/{$game->id}");

    $response->assertOk();
    expect($response->json('turnIndex'))->toBe(2);
    expect($response->json('discardTop'))->toBe('8H');
    expect($response->json('players.0.handCount'))->toBe(11);
    expect($response->json('players.1.handCount'))->toBe(13);
});

it('returns a null discardTop when no card has been discarded yet', function () {
    $game = Game::create(['id' => 'game-no-discard', 'decks' => 1, 'target_score' => 1500]);
    Player::create(['id' => 'player-no-discard', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);

    $response = $this->getJson("/api/games/{$game->id}");

    $response->assertOk();
    expect($response->json('discardTop'))->toBeNull();
});
