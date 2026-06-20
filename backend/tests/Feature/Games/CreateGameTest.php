<?php

use App\Models\Game;
use App\Models\Player;

it('creates a game with 2 players and persists it', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 2,
        'targetScore' => 3000,
        'players' => ['Ana', 'Bruno'],
    ]);

    $response->assertCreated();
    $id = $response->json('id');
    expect($id)->toBeString();

    $game = Game::find($id);
    expect($game)->not->toBeNull();
    expect($game->decks)->toBe(2);
    expect($game->target_score)->toBe(3000);

    $players = Player::where('game_id', $id)->orderBy('seat_index')->get();
    expect($players)->toHaveCount(2);
    expect($players[0]->seat_index)->toBe(0);
    expect($players[0]->name)->toBe('Ana');
    expect($players[1]->seat_index)->toBe(1);
    expect($players[1]->name)->toBe('Bruno');
});

it('creates a game with 4 players', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 1,
        'targetScore' => 1500,
        'players' => ['Ana', 'Bruno', 'Carla', 'Diego'],
    ]);

    $response->assertCreated();
    $id = $response->json('id');

    $players = Player::where('game_id', $id)->get();
    expect($players)->toHaveCount(4);
});

it('rejects an invalid number of decks', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 5,
        'targetScore' => 3000,
        'players' => ['Ana', 'Bruno'],
    ]);

    $response->assertStatus(422);
});

it('rejects a target score below 100', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 2,
        'targetScore' => 50,
        'players' => ['Ana', 'Bruno'],
    ]);

    $response->assertStatus(422);
});

it('rejects a player count outside 2 or 4', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 2,
        'targetScore' => 3000,
        'players' => ['Ana', 'Bruno', 'Carla'],
    ]);

    $response->assertStatus(422);
});
