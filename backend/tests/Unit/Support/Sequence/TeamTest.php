<?php

use App\Exceptions\PlayerNotInTeamException;
use App\Models\Game;
use App\Models\Player;
use App\Support\Sequence\Team;
use Tests\TestCase;

uses(TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves team A for even seat indexes and B for odd', function () {
    $game = Game::create(['id' => 'game-1', 'decks' => 1, 'target_score' => 1000]);
    $playerA = Player::create(['id' => 'p-0', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    $playerB = Player::create(['id' => 'p-1', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']);

    expect(Team::of($playerA))->toBe('A');
    expect(Team::of($playerB))->toBe('B');
});

it('throws when a player does not belong to the expected team', function () {
    $game = Game::create(['id' => 'game-2', 'decks' => 1, 'target_score' => 1000]);
    $player = Player::create(['id' => 'p-2', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']);

    expect(fn () => Team::ensure($player, 'A'))->toThrow(PlayerNotInTeamException::class);
});

it('does not throw when the player matches the expected team', function () {
    $game = Game::create(['id' => 'game-3', 'decks' => 1, 'target_score' => 1000]);
    $player = Player::create(['id' => 'p-3', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);

    Team::ensure($player, 'A');
})->throwsNoExceptions();

it('lists player ids belonging to a team', function () {
    $game = Game::create(['id' => 'game-4', 'decks' => 1, 'target_score' => 1000]);
    Player::create(['id' => 'p-4', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    Player::create(['id' => 'p-5', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']);
    Player::create(['id' => 'p-6', 'game_id' => $game->id, 'seat_index' => 2, 'name' => 'Carla']);

    expect(Team::playerIds($game->id, 'A'))->toEqualCanonicalizing(['p-4', 'p-6']);
    expect(Team::playerIds($game->id, 'B'))->toEqualCanonicalizing(['p-5']);
});
