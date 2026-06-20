<?php

use App\Models\Game;
use App\Models\Player;

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
