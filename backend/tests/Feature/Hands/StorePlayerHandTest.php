<?php

use App\Models\Card;
use App\Models\Player;

function createTestGame(int $decks = 2, array $players = ['Ana', 'Bruno']): array
{
    $response = test()->postJson('/api/games', [
        'decks' => $decks,
        'targetScore' => 1500,
        'players' => $players,
    ]);

    $gameId = $response->json('id');
    $players = Player::where('game_id', $gameId)->orderBy('seat_index')->get();

    return [$gameId, $players];
}

it('registers a 13-card hand for a player', function () {
    [$gameId, $players] = createTestGame(decks: 2);
    $player = $players->first();

    $cards = ['AS', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS'];

    $response = $this->postJson("/api/players/{$player->id}/hand", ['cards' => $cards]);

    $response->assertOk();
    expect($response->json('cards'))->toBe($cards);

    $handCards = Card::where('player_id', $player->id)->where('status', 'hand')->pluck('code')->sort()->values()->all();
    $expected = collect($cards)->sort()->values()->all();
    expect($handCards)->toBe($expected);
});

it('accepts duplicate cards up to the number of decks in play', function () {
    [$gameId, $players] = createTestGame(decks: 2);
    $player = $players->first();

    $cards = ['7C', '7C', '8C', '8C', 'W', 'W', 'W', 'W', '2D', '3D', '4D', '5D', '6D'];

    $response = $this->postJson("/api/players/{$player->id}/hand", ['cards' => $cards]);

    $response->assertOk();
    expect(Card::where('player_id', $player->id)->where('code', '7C')->count())->toBe(2);
    expect(Card::where('player_id', $player->id)->where('code', 'W')->count())->toBe(4);
});

it('rejects a hand that asks for more copies of a card than the deck holds', function () {
    [$gameId, $players] = createTestGame(decks: 1);
    $player = $players->first();

    $cards = ['7C', '7C', '8C', '9C', 'TC', 'JC', 'QC', 'KC', 'AC', '2C', '3C', '4C', '5C'];

    $response = $this->postJson("/api/players/{$player->id}/hand", ['cards' => $cards]);

    $response->assertStatus(422);
    expect(Card::where('player_id', $player->id)->count())->toBe(0);
});

it('rejects a hand that does not have exactly 13 cards', function () {
    [$gameId, $players] = createTestGame(decks: 2);
    $player = $players->first();

    $response = $this->postJson("/api/players/{$player->id}/hand", [
        'cards' => ['AS', '2S', '3S'],
    ]);

    $response->assertStatus(422);
});

it('rejects an invalid card code', function () {
    [$gameId, $players] = createTestGame(decks: 2);
    $player = $players->first();

    $cards = ['ZZ', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS'];

    $response = $this->postJson("/api/players/{$player->id}/hand", ['cards' => $cards]);

    $response->assertStatus(422);
});

it('replaces a previously registered hand, releasing the old cards back to the deck', function () {
    [$gameId, $players] = createTestGame(decks: 2);
    $player = $players->first();

    $firstHand = ['AS', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS'];
    $this->postJson("/api/players/{$player->id}/hand", ['cards' => $firstHand])->assertOk();

    $secondHand = ['AH', '2H', '3H', '4H', '5H', '6H', '7H', '8H', '9H', 'TH', 'JH', 'QH', 'KH'];
    $this->postJson("/api/players/{$player->id}/hand", ['cards' => $secondHand])->assertOk();

    expect(Card::where('player_id', $player->id)->where('status', 'hand')->count())->toBe(13);
    expect(Card::where('player_id', $player->id)->where('code', 'AS')->where('status', 'hand')->count())->toBe(0);
    expect(Card::where('game_id', $gameId)->where('code', 'AS')->where('status', 'deck')->count())->toBe(2);
});

it('does not let a player claim cards belonging to a different game', function () {
    [$gameIdOne, $playersOne] = createTestGame(decks: 1, players: ['Ana', 'Bruno']);
    [$gameIdTwo, $playersTwo] = createTestGame(decks: 1, players: ['Carla', 'Diego']);

    $player = $playersOne->first();
    $cards = ['AS', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS'];

    $response = $this->postJson("/api/players/{$player->id}/hand", ['cards' => $cards]);

    $response->assertOk();
    expect(Card::where('game_id', $gameIdOne)->where('status', 'hand')->count())->toBe(13);
    expect(Card::where('game_id', $gameIdTwo)->where('status', 'hand')->count())->toBe(0);
});
