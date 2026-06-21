<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Str;

// tests/Unit/Support/Cards/CardPoolTest.php declares an identical global makeHandCard()
// helper. Pest loads every test file into the same process, so declaring it unconditionally
// here too would be a fatal "Cannot redeclare" error when the full suite runs — guard it.
if (! function_exists('makeHandCard')) {
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
}

/**
 * @return array{0: Game, 1: Player[]}
 */
function makeGameWithPlayers(int $playerCount = 2): array
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

    return [$game, $players];
}

it('creates a sequence with all face cards', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', '4H', '5H'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '4H', '5H'],
    ]);

    $response->assertCreated();
    expect($response->json('team'))->toBe('A');
    expect($response->json('status'))->toBe('forming');
    expect($response->json('cards'))->toBe([
        ['code' => '3H', 'role' => 'face'],
        ['code' => '4H', 'role' => 'face'],
        ['code' => '5H', 'role' => 'face'],
    ]);

    $sequence = \App\Models\Sequence::find($response->json('id'));
    expect($sequence->suit)->toBe('H');
    expect($sequence->start_rank)->toBe('3');
    expect($sequence->is_ace_trinca)->toBeFalse();
});

it('resolves a joker substituting a position as wild', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', 'W', '5H'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', 'W', '5H'],
    ]);

    $response->assertCreated();
    expect($response->json('cards.1'))->toBe(['code' => 'W', 'role' => 'wild']);
});

it('resolves a two of a different suit off its natural slot as wild', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', '2D', '5H'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '2D', '5H'],
    ]);

    $response->assertCreated();
    expect($response->json('cards.1'))->toBe(['code' => '2D', 'role' => 'wild']);
});

it('resolves the matching-suit two at the two position as face', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['AH', '2H', '3H'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => 'A',
        'acesTrinca' => false,
        'cards' => ['AH', '2H', '3H'],
    ]);

    $response->assertCreated();
    expect($response->json('cards.1'))->toBe(['code' => '2H', 'role' => 'face']);
});

it('creates an ace trinca with mixed suits', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['AS', 'AH', 'AD'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => null,
        'acesTrinca' => true,
        'cards' => ['AS', 'AH', 'AD'],
    ]);

    $response->assertCreated();
    expect($response->json('team'))->toBe('A');
    expect(collect($response->json('cards'))->pluck('role')->unique()->all())->toBe(['face']);

    $sequence = \App\Models\Sequence::find($response->json('id'));
    expect($sequence->is_ace_trinca)->toBeTrue();
    expect($sequence->suit)->toBeNull();
    expect($sequence->start_rank)->toBeNull();
});

it('accepts cards contributed from a teammates hand', function () {
    [$game, $players] = makeGameWithPlayers(4);
    $playerA = $players[0];
    $playerC = $players[2]; // seat 2, also team A
    makeHandCard($game->id, $playerA->id, '3H');
    makeHandCard($game->id, $playerC->id, '4H');
    makeHandCard($game->id, $playerA->id, '5H');

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $playerA->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '4H', '5H'],
    ]);

    $response->assertCreated();
});

it('marks a 7-card sequence with no wild two as clean', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    $cards = ['3H', '4H', '5H', '6H', '7H', '8H', 'W'];
    foreach ($cards as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => $cards,
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('clean');
});

it('marks a 7-card sequence with a wild two as dirty', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    $cards = ['3H', '4H', '5H', '6H', '7H', '8H', '2D'];
    foreach ($cards as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => $cards,
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('dirty');
});

it('rejects fewer than 3 cards', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    makeHandCard($game->id, $player->id, '3H');
    makeHandCard($game->id, $player->id, '4H');

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '4H'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('sequence_too_short');
});

it('rejects a card that does not match the position and is not a wildcard', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', '9C', '5H'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '9C', '5H'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('invalid_sequence_card');
});

it('rejects two wild jokers in the same sequence', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', 'W', 'W'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', 'W', 'W'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('max_wild_joker_exceeded');
});

it('rejects two wild twos in the same sequence', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', '2D', '2C'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '2D', '2C'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('max_wild_two_exceeded');
});

it('rejects a wild joker coexisting with a wild two', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['3H', 'W', '2D'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', 'W', '2D'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('wildcard_coexistence');
});

it('rejects a sequence that would pass K', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['QH', 'KH', 'AH'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => 'Q',
        'acesTrinca' => false,
        'cards' => ['QH', 'KH', 'AH'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('sequence_rank_out_of_bounds');
});

it('rejects insufficient cards in the pool for a requested code', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    makeHandCard($game->id, $player->id, '3H');
    makeHandCard($game->id, $player->id, '4H');

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => 'H',
        'startRank' => '3',
        'acesTrinca' => false,
        'cards' => ['3H', '4H', '5H'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('insufficient_cards_in_pool');
});

it('rejects an ace trinca card that is not an ace', function () {
    [$game, $players] = makeGameWithPlayers();
    $player = $players[0];
    foreach (['AS', 'AH', '2D'] as $code) {
        makeHandCard($game->id, $player->id, $code);
    }

    $response = $this->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $player->id,
        'suit' => null,
        'acesTrinca' => true,
        'cards' => ['AS', 'AH', '2D'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('invalid_ace_trinca_card');
});
