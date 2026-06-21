<?php

use App\Exceptions\InsufficientCardsInPoolException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Support\Cards\CardPool;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeHandCard(string $gameId, string $playerId, string $code): Card
{
    return Card::create([
        'id' => (string) Str::uuid(),
        'game_id' => $gameId,
        'code' => $code,
        'status' => 'hand',
        'player_id' => $playerId,
    ]);
}

it('claims the requested quantity of each code from the given players hands', function () {
    $game = Game::create(['id' => 'game-1', 'decks' => 2, 'target_score' => 1000]);
    $player = Player::create(['id' => 'p-1', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    makeHandCard($game->id, $player->id, '7C');
    makeHandCard($game->id, $player->id, '7C');
    makeHandCard($game->id, $player->id, 'W');

    $claimed = CardPool::claimFromHands(['7C', '7C', 'W'], [$player->id]);

    expect($claimed)->toHaveCount(3);
    expect($claimed->where('code', '7C'))->toHaveCount(2);
    expect($claimed->where('code', 'W'))->toHaveCount(1);
    // claiming does not mutate status itself — caller's responsibility
    expect(Card::where('status', 'hand')->count())->toBe(3);
});

it('claims across multiple player ids (teammates contributing)', function () {
    $game = Game::create(['id' => 'game-2', 'decks' => 1, 'target_score' => 1000]);
    $playerA = Player::create(['id' => 'p-2', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    $playerC = Player::create(['id' => 'p-3', 'game_id' => $game->id, 'seat_index' => 2, 'name' => 'Carla']);
    makeHandCard($game->id, $playerA->id, '5H');
    makeHandCard($game->id, $playerC->id, '6H');

    $claimed = CardPool::claimFromHands(['5H', '6H'], [$playerA->id, $playerC->id]);

    expect($claimed)->toHaveCount(2);
});

it('throws when a code does not have enough copies among the given players', function () {
    $game = Game::create(['id' => 'game-3', 'decks' => 1, 'target_score' => 1000]);
    $player = Player::create(['id' => 'p-4', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    makeHandCard($game->id, $player->id, '7C');

    expect(fn () => CardPool::claimFromHands(['7C', '7C'], [$player->id]))
        ->toThrow(InsufficientCardsInPoolException::class);
});

it('does not claim a code held by a player outside the given list', function () {
    $game = Game::create(['id' => 'game-4', 'decks' => 1, 'target_score' => 1000]);
    $player = Player::create(['id' => 'p-5', 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']);
    $otherPlayer = Player::create(['id' => 'p-6', 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']);
    makeHandCard($game->id, $otherPlayer->id, '7C');

    expect(fn () => CardPool::claimFromHands(['7C'], [$player->id]))
        ->toThrow(InsufficientCardsInPoolException::class);
});
