# Registrar Jogada + Avançar Turno Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let any player's turn be registered (compra do monte/lixo, descarte obrigatório exceto ao bater, baixar-na-mesa como contagem) and advance `turn_index`, with a minimal frontend screen to drive it, building on the existing deck/hand/sequence infrastructure.

**Architecture:** `Game` gains `turn_index`; `Player` gains `hand_count`. Only seat 0 has individually-identified hand cards (`Card.status='hand'`); every other player's hand is opaque — their draws/discards/lowers are tracked only as a `hand_count` integer, with revealed codes (discards) claimed straight from the undifferentiated `status='deck'` pool instead of from a `status='hand'` row that doesn't exist for them. A new `plays` table logs each turn. One endpoint (`POST /api/games/{game}/plays`) does compra → (baixar como contagem) → descarte obrigatório → avança turno, all in one transaction. Frontend: a new `GameCardPicker` component generalizes the suit-tabs+grid picker already built for hand registration (single-value mode for this feature, multi-value mode preserving the existing hand-registration behavior exactly), plus a new minimal play-registration page.

**Tech Stack:** Laravel 13 + Pest (backend), Nuxt 4 + Vue 3 + Playwright (frontend) — same stack as prior features, no new packages.

## Global Constraints

- Backend: always write the Pest test first (RED), then minimal implementation (GREEN).
- Pattern: Controller → Action (`handle()` via `AsAction`, `::run()`) → Data (input) → Resource (output).
- Business-rule violations throw a specific `App\Exceptions\...Exception` subclass extending `App\Exceptions\DomainException` (never a generic `ValidationException`), rendered by the existing global handler in `bootstrap/app.php` as `{error, message, context}`.
- Frontend: write the Playwright e2e test first (RED) before implementing new pages/components. Every interactive element gets a `data-testid`.
- "Rastreado" = `seat_index === 0`. Everyone else is "não-rastreado": no individual `Card.status='hand'` rows, only the `hand_count` counter.
- Card code format: `[RANK][SUIT]` or `W` (joker, no suit).
- Git commits: one line, semantic prefix, no body, never mention Claude.

---

## Task 1: `games.turn_index`, `players.hand_count`, `cards.discard_position`

**Files:**
- Create: `backend/database/migrations/2026_06_21_130000_add_turn_index_to_games_table.php`
- Create: `backend/database/migrations/2026_06_21_130001_add_hand_count_to_players_table.php`
- Create: `backend/database/migrations/2026_06_21_130002_add_discard_position_to_cards_table.php`
- Modify: `backend/app/Models/Game.php`
- Modify: `backend/app/Models/Player.php`
- Modify: `backend/tests/Feature/Games/CreateGameTest.php`

**Interfaces:**
- Produces: `games.turn_index` (int, default 0), `players.hand_count` (int, default 13), `cards.discard_position` (nullable int). `Game`/`Player` `#[Fillable]` updated to include the new columns.

- [ ] **Step 1: Write the failing test**

Add to `backend/tests/Feature/Games/CreateGameTest.php` (append; add `use App\Models\Player;` if not already imported — it already is):

```php
it('initializes every player with a hand count of 13 and the game at turn 0', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 2,
        'targetScore' => 3000,
        'players' => ['Ana', 'Bruno'],
    ]);

    $id = $response->json('id');

    expect(Game::find($id)->turn_index)->toBe(0);
    expect(Player::where('game_id', $id)->pluck('hand_count')->all())->toBe([13, 13]);
});
```

(`use App\Models\Game;` is already imported in this file.)

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/CreateGameTest.php`
Expected: FAIL — `turn_index`/`hand_count` columns don't exist yet (SQL error).

- [ ] **Step 3: Write the three migrations**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('turn_index')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('turn_index');
        });
    }
};
```

Save as `backend/database/migrations/2026_06_21_130000_add_turn_index_to_games_table.php`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('hand_count')->default(13);
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('hand_count');
        });
    }
};
```

Save as `backend/database/migrations/2026_06_21_130001_add_hand_count_to_players_table.php`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('discard_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('discard_position');
        });
    }
};
```

Save as `backend/database/migrations/2026_06_21_130002_add_discard_position_to_cards_table.php`.

- [ ] **Step 4: Update `Game` and `Player` models**

In `backend/app/Models/Game.php`, change the `#[Fillable]` line to:

```php
#[Fillable(['id', 'decks', 'target_score', 'turn_index'])]
```

In `backend/app/Models/Player.php`, change the `#[Fillable]` line to:

```php
#[Fillable(['id', 'game_id', 'seat_index', 'name', 'hand_count'])]
```

- [ ] **Step 5: Run the migrations**

Run: `cd backend && php artisan migrate`
Expected: all three new migrations run with no errors.

- [ ] **Step 6: Run the test to verify it passes**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/CreateGameTest.php`
Expected: all tests PASS.

- [ ] **Step 7: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 8: Commit**

```bash
cd backend
git add database/migrations/2026_06_21_130000_add_turn_index_to_games_table.php database/migrations/2026_06_21_130001_add_hand_count_to_players_table.php database/migrations/2026_06_21_130002_add_discard_position_to_cards_table.php app/Models/Game.php app/Models/Player.php tests/Feature/Games/CreateGameTest.php
git commit -m "feat: add turn_index, hand_count, and discard_position columns"
```

---

## Task 2: `plays` table + `Play` model

**Files:**
- Create: `backend/database/migrations/2026_06_21_130003_create_plays_table.php`
- Create: `backend/app/Models/Play.php`
- Modify: `backend/app/Models/Game.php`
- Modify: `backend/app/Models/Player.php`

**Interfaces:**
- Produces: `Play` model (`id`, `game_id`, `player_id`, `turn_index`, `drew_from`, `discarded_code` nullable, `lowered_count`), `belongsTo(Game)`, `belongsTo(Player)`. `Game::plays(): HasMany`, `Player::plays(): HasMany`.

No test of its own (pure infrastructure, exercised by Task 4) — verify via migration run + full suite.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->references('id')->on('games');
            $table->foreignUuid('player_id')->references('id')->on('players');
            $table->unsignedInteger('turn_index');
            $table->string('drew_from');
            $table->string('discarded_code')->nullable();
            $table->unsignedInteger('lowered_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
    }
};
```

Save as `backend/database/migrations/2026_06_21_130003_create_plays_table.php`.

- [ ] **Step 2: Create the `Play` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'game_id', 'player_id', 'turn_index', 'drew_from', 'discarded_code', 'lowered_count'])]
class Play extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
```

Save as `backend/app/Models/Play.php`.

- [ ] **Step 3: Add `plays()` to `Game` and `Player`**

In `backend/app/Models/Game.php`, add alongside `players()`/`cards()`/`sequences()`:

```php
    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }
```

In `backend/app/Models/Player.php`, add alongside `cards()` (add `use Illuminate\Database\Eloquent\Relations\HasMany;` if not already imported — it already is):

```php
    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }
```

- [ ] **Step 4: Run the migration**

Run: `cd backend && php artisan migrate`
Expected: runs with no errors.

- [ ] **Step 5: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
cd backend
git add database/migrations/2026_06_21_130003_create_plays_table.php app/Models/Play.php app/Models/Game.php app/Models/Player.php
git commit -m "feat: add plays table to log registered turns"
```

---

## Task 3: New domain exceptions for play registration

**Files:**
- Create: `backend/app/Exceptions/Play/NotPlayersTurnException.php`
- Create: `backend/app/Exceptions/Play/DrawnCodeRequiredException.php`
- Create: `backend/app/Exceptions/Play/DiscardPileEmptyException.php`
- Create: `backend/app/Exceptions/Play/DiscardRequiredException.php`
- Create: `backend/app/Exceptions/Play/NothingToDiscardException.php`
- Create: `backend/app/Exceptions/Play/LoweredCountExceedsHandException.php`

**Interfaces:**
- Produces: 6 exception classes, all extending `App\Exceptions\DomainException` (Task 2 of the previous "mesa e sequências" feature, already merged), relying on its default `errorCode()`/`status()`.
- Consumed by: Task 4 (`RegisterPlay` action).

No standalone test for this task (each is a minimal, single-responsibility class exercised by Task 4's Feature tests) — consistent with how the equivalent sequence exceptions were handled.

- [ ] **Step 1: Create `NotPlayersTurnException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class NotPlayersTurnException extends DomainException
{
    public function __construct(
        private readonly string $providedPlayerId,
        private readonly string $expectedPlayerId,
    ) {
        parent::__construct("Não é a vez do jogador \"{$providedPlayerId}\" — é a vez de \"{$expectedPlayerId}\".");
    }

    public function context(): array
    {
        return ['providedPlayerId' => $this->providedPlayerId, 'expectedPlayerId' => $this->expectedPlayerId];
    }
}
```

Save as `backend/app/Exceptions/Play/NotPlayersTurnException.php`.

- [ ] **Step 2: Create `DrawnCodeRequiredException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DrawnCodeRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('É necessário informar a carta comprada do monte.');
    }
}
```

Save as `backend/app/Exceptions/Play/DrawnCodeRequiredException.php`.

- [ ] **Step 3: Create `DiscardPileEmptyException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DiscardPileEmptyException extends DomainException
{
    public function __construct()
    {
        parent::__construct('O lixo está vazio — não há carta para pegar.');
    }
}
```

Save as `backend/app/Exceptions/Play/DiscardPileEmptyException.php`.

- [ ] **Step 4: Create `DiscardRequiredException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class DiscardRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('É necessário descartar uma carta nesta jogada.');
    }
}
```

Save as `backend/app/Exceptions/Play/DiscardRequiredException.php`.

- [ ] **Step 5: Create `NothingToDiscardException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class NothingToDiscardException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A mão ficaria vazia — não há carta para descartar.');
    }
}
```

Save as `backend/app/Exceptions/Play/NothingToDiscardException.php`.

- [ ] **Step 6: Create `LoweredCountExceedsHandException`**

```php
<?php

namespace App\Exceptions\Play;

use App\Exceptions\DomainException;

class LoweredCountExceedsHandException extends DomainException
{
    public function __construct(
        private readonly int $loweredCount,
        private readonly int $available,
    ) {
        parent::__construct("Não é possível baixar {$loweredCount} cartas — a mão só tem {$available}.");
    }

    public function context(): array
    {
        return ['loweredCount' => $this->loweredCount, 'available' => $this->available];
    }
}
```

Save as `backend/app/Exceptions/Play/LoweredCountExceedsHandException.php`.

- [ ] **Step 7: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS (these classes aren't exercised yet, just confirm nothing broke — e.g. no namespace collision).

- [ ] **Step 8: Commit**

```bash
cd backend
git add app/Exceptions/Play
git commit -m "feat: add domain exceptions for play registration"
```

---

## Task 4: `POST /api/games/{game}/plays` — register a play and advance the turn

**Files:**
- Create: `backend/tests/Feature/Plays/RegisterPlayTest.php`
- Create: `backend/app/Data/Play/RegisterPlayData.php`
- Create: `backend/app/Actions/Play/RegisterPlay.php`
- Create: `backend/app/Http/Resources/PlayResource.php`
- Create: `backend/app/Http/Controllers/Api/PlayController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `App\Exceptions\DomainException` and `App\Exceptions\InsufficientCardsInPoolException` (already exist), the 6 exceptions from Task 3.
- Produces: `RegisterPlay::run(Game $game, RegisterPlayData $data): array` returning `{playerId, turnIndex, drewFrom, discardedCode, loweredCount, handCountAfter, nextPlayerId}`. `PlayController::store(Game $game, RegisterPlayData $data)`.

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Plays/RegisterPlayTest.php`:

```php
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

it('lets a tracked player pick up the top of the discard pile', function () {
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
    playGiveHandCard($game->id, $player1->id, '7C');
    $game->update(['turn_index' => 1]);

    $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player1->id,
        'drewFrom' => 'monte',
        'discardedCode' => '7C',
        'loweredCount' => 0,
    ])->assertOk();

    $response = $this->postJson("/api/games/{$game->id}/plays", [
        'playerId' => $player0->id,
        'drewFrom' => 'lixo',
        'discardedCode' => '7C',
        'loweredCount' => 0,
    ]);

    $response->assertOk();
    $card = Card::where('game_id', $game->id)->where('code', '7C')->first();
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Plays/RegisterPlayTest.php`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Create `RegisterPlayData`**

```php
<?php

namespace App\Data\Play;

use Spatie\LaravelData\Data;

class RegisterPlayData extends Data
{
    public function __construct(
        public string $playerId,
        public string $drewFrom,
        public ?string $drawnCode,
        public ?string $discardedCode,
        public int $loweredCount = 0,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'drewFrom' => ['required', 'in:monte,lixo'],
            'drawnCode' => ['nullable', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'discardedCode' => ['nullable', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'loweredCount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

Save as `backend/app/Data/Play/RegisterPlayData.php`.

- [ ] **Step 4: Create the `RegisterPlay` action**

```php
<?php

namespace App\Actions\Play;

use App\Data\Play\RegisterPlayData;
use App\Exceptions\InsufficientCardsInPoolException;
use App\Exceptions\Play\DiscardPileEmptyException;
use App\Exceptions\Play\DiscardRequiredException;
use App\Exceptions\Play\DrawnCodeRequiredException;
use App\Exceptions\Play\LoweredCountExceedsHandException;
use App\Exceptions\Play\NotPlayersTurnException;
use App\Exceptions\Play\NothingToDiscardException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Play;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterPlay
{
    use AsAction;

    public Game $game;

    public Player $player;

    public bool $isTracked;

    /**
     * @return array<string, mixed>
     */
    public function handle(Game $game, RegisterPlayData $data): array
    {
        $this->game = $game;

        $players = $game->players()->orderBy('seat_index')->get();
        $currentPlayer = $players[$game->turn_index % $players->count()];

        if ($currentPlayer->id !== $data->playerId) {
            throw new NotPlayersTurnException($data->playerId, $currentPlayer->id);
        }

        $this->player = $currentPlayer;
        $this->isTracked = $this->player->seat_index === 0;

        return DB::transaction(function () use ($data, $players) {
            $this->handleDraw($data);

            $remaining = $this->player->hand_count + 1 - $data->loweredCount;

            if ($remaining < 0) {
                throw new LoweredCountExceedsHandException($data->loweredCount, $this->player->hand_count + 1);
            }

            $this->handleDiscard($data, $remaining);

            $turnIndexBefore = $this->game->turn_index;

            Play::create([
                'id' => (string) Str::uuid(),
                'game_id' => $this->game->id,
                'player_id' => $this->player->id,
                'turn_index' => $turnIndexBefore,
                'drew_from' => $data->drewFrom,
                'discarded_code' => $data->discardedCode,
                'lowered_count' => $data->loweredCount,
            ]);

            $this->game->update(['turn_index' => $turnIndexBefore + 1]);

            $nextPlayer = $players[($turnIndexBefore + 1) % $players->count()];

            return [
                'playerId' => $this->player->id,
                'turnIndex' => $turnIndexBefore,
                'drewFrom' => $data->drewFrom,
                'discardedCode' => $data->discardedCode,
                'loweredCount' => $data->loweredCount,
                'handCountAfter' => $this->player->hand_count,
                'nextPlayerId' => $nextPlayer->id,
            ];
        });
    }

    public function handleDraw(RegisterPlayData $data): void
    {
        if ($data->drewFrom === 'monte') {
            if (! $this->isTracked) {
                return;
            }

            if ($data->drawnCode === null) {
                throw new DrawnCodeRequiredException();
            }

            $card = Card::where('game_id', $this->game->id)
                ->where('code', $data->drawnCode)
                ->where('status', 'deck')
                ->lockForUpdate()
                ->first();

            if ($card === null) {
                throw new InsufficientCardsInPoolException($data->drawnCode, 1, 0);
            }

            $card->update(['status' => 'hand', 'player_id' => $this->player->id]);

            return;
        }

        $top = Card::where('game_id', $this->game->id)
            ->where('status', 'discard')
            ->orderByDesc('discard_position')
            ->lockForUpdate()
            ->first();

        if ($top === null) {
            throw new DiscardPileEmptyException();
        }

        if ($this->isTracked) {
            $top->update(['status' => 'hand', 'player_id' => $this->player->id, 'discard_position' => null]);
        } else {
            $top->update(['status' => 'deck', 'player_id' => null, 'discard_position' => null]);
        }
    }

    public function handleDiscard(RegisterPlayData $data, int $remaining): void
    {
        if ($remaining === 0) {
            if ($data->discardedCode !== null) {
                throw new NothingToDiscardException();
            }

            $this->player->update(['hand_count' => 0]);

            return;
        }

        if ($data->discardedCode === null) {
            throw new DiscardRequiredException();
        }

        $query = Card::where('game_id', $this->game->id)->where('code', $data->discardedCode);

        $query = $this->isTracked
            ? $query->where('status', 'hand')->where('player_id', $this->player->id)
            : $query->where('status', 'deck');

        $card = $query->lockForUpdate()->first();

        if ($card === null) {
            throw new InsufficientCardsInPoolException($data->discardedCode, 1, 0);
        }

        $nextPosition = (Card::where('game_id', $this->game->id)->where('status', 'discard')->max('discard_position') ?? 0) + 1;

        $card->update([
            'status' => 'discard',
            'player_id' => $this->player->id,
            'discard_position' => $nextPosition,
        ]);

        $this->player->update(['hand_count' => $remaining - 1]);
    }
}
```

Save as `backend/app/Actions/Play/RegisterPlay.php`.

- [ ] **Step 5: Create `PlayResource`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
```

Save as `backend/app/Http/Resources/PlayResource.php`.

- [ ] **Step 6: Create `PlayController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Play\RegisterPlay;
use App\Data\Play\RegisterPlayData;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlayResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class PlayController extends Controller
{
    public function store(Game $game, RegisterPlayData $data): JsonResponse
    {
        $result = RegisterPlay::run($game, $data);

        return PlayResource::make($result)->response();
    }
}
```

Save as `backend/app/Http/Controllers/Api/PlayController.php`.

- [ ] **Step 7: Add the route**

In `backend/routes/api.php`, add the import and route:

```php
use App\Http\Controllers\Api\PlayController;
```

```php
Route::post('/games/{game}/plays', [PlayController::class, 'store']);
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Plays/RegisterPlayTest.php`
Expected: all tests PASS.

- [ ] **Step 9: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 10: Commit**

```bash
cd backend
git add tests/Feature/Plays/RegisterPlayTest.php app/Data/Play/RegisterPlayData.php app/Actions/Play/RegisterPlay.php app/Http/Resources/PlayResource.php app/Http/Controllers/Api/PlayController.php routes/api.php
git commit -m "feat: add POST /api/games/{game}/plays to register a play and advance the turn"
```

---

## Task 5: Extend `GET /api/games/{game}` with `turnIndex`, `handCount`, `discardTop`

**Files:**
- Modify: `backend/app/Data/Player/PlayerData.php`
- Modify: `backend/app/Data/Game/GameDetailData.php`
- Modify: `backend/app/Actions/Game/ShowGame.php`
- Modify: `backend/app/Http/Resources/GameDetailResource.php`
- Modify: `backend/tests/Feature/Games/ShowGameTest.php`

**Interfaces:**
- Produces: `GET /api/games/{game}` response gains `turnIndex` (int) and `discardTop` (`string|null`, the top discard's code); each entry in `players[]` gains `handCount` (int).
- Consumed by: Task 8 (the new play-registration page).

- [ ] **Step 1: Write the failing test**

Append to `backend/tests/Feature/Games/ShowGameTest.php` (the file already imports `App\Models\Game`/`App\Models\Player`; add `use App\Models\Card;` and `use Illuminate\Support\Str;` if not present):

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/ShowGameTest.php`
Expected: FAIL — `turnIndex`/`discardTop`/`handCount` keys are missing from the response.

- [ ] **Step 3: Update `PlayerData`**

```php
<?php

namespace App\Data\Player;

use Spatie\LaravelData\Data;

class PlayerData extends Data
{
    public function __construct(
        public string $id,
        public int $seatIndex,
        public string $name,
        public int $handCount,
    ) {}
}
```

Save as `backend/app/Data/Player/PlayerData.php`.

- [ ] **Step 4: Update `GameDetailData`**

```php
<?php

namespace App\Data\Game;

use App\Data\Player\PlayerData;
use Spatie\LaravelData\Data;

class GameDetailData extends Data
{
    public function __construct(
        public string $id,
        public int $decks,
        public int $targetScore,
        public int $turnIndex,
        public ?string $discardTop,
        /** @var PlayerData[] */
        public array $players,
    ) {}
}
```

Save as `backend/app/Data/Game/GameDetailData.php`.

- [ ] **Step 5: Update `ShowGame`**

```php
<?php

namespace App\Actions\Game;

use App\Data\Game\GameDetailData;
use App\Data\Player\PlayerData;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGame
{
    use AsAction;

    public Game $game;

    public function handle(Game $game): GameDetailData
    {
        $this->game = $game;
        $this->loadPlayers();

        return $this->toData();
    }

    public function loadPlayers(): void
    {
        $this->game->loadMissing(['players' => fn ($query) => $query->orderBy('seat_index')]);
    }

    public function toData(): GameDetailData
    {
        $discardTop = Card::where('game_id', $this->game->id)
            ->where('status', 'discard')
            ->orderByDesc('discard_position')
            ->first();

        return new GameDetailData(
            id: $this->game->id,
            decks: $this->game->decks,
            targetScore: $this->game->target_score,
            turnIndex: $this->game->turn_index,
            discardTop: $discardTop?->code,
            players: $this->game->players->map(fn (Player $player) => new PlayerData(
                id: $player->id,
                seatIndex: $player->seat_index,
                name: $player->name,
                handCount: $player->hand_count,
            ))->all(),
        );
    }
}
```

Save as `backend/app/Actions/Game/ShowGame.php`.

- [ ] **Step 6: Update `GameDetailResource`**

```php
<?php

namespace App\Http\Resources;

use App\Data\Game\GameDetailData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GameDetailData */
class GameDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decks' => $this->decks,
            'targetScore' => $this->targetScore,
            'turnIndex' => $this->turnIndex,
            'discardTop' => $this->discardTop,
            'players' => $this->players,
        ];
    }
}
```

Save as `backend/app/Http/Resources/GameDetailResource.php`.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/ShowGameTest.php`
Expected: all tests PASS.

- [ ] **Step 8: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 9: Commit**

```bash
cd backend
git add app/Data/Player/PlayerData.php app/Data/Game/GameDetailData.php app/Actions/Game/ShowGame.php app/Http/Resources/GameDetailResource.php tests/Feature/Games/ShowGameTest.php
git commit -m "feat: extend GET /api/games/{game} with turn, hand counts, and discard top"
```

---

## Task 6: Extract `GameCardPicker`, generalize `GameCardTile`, refactor `initial-hand.vue`

**Files:**
- Modify: `frontend/app/components/game/card-tile.vue`
- Create: `frontend/app/components/game/card-picker.vue`
- Modify: `frontend/app/pages/games/[id]/initial-hand.vue`

**Interfaces:**
- Produces: `GameCardTile` gains `testIdPrefix?: string` (default `'hand-card'`), testid becomes `` `${testIdPrefix}-${variant}-${code}` `` (identical to today's hardcoded `hand-card-${variant}-${code}` when the prop is omitted). `GameCardPicker` (`<GameCardPicker>` per Nuxt's `components/game/` auto-import convention): props `modelValue: string | string[]`, `multiple?: boolean` (default `false`), `decks?: number` (default `1`, only used when `multiple`), `testIdPrefix?: string` (default `'card-picker'`); emits `update:modelValue`. In `multiple` mode it behaves exactly like the picker inline in `initial-hand.vue` today (suit tabs + grid + tray + deck-limit note, duplicates capped by `decks`). In single mode (`multiple=false`) it's grid-only: clicking a card **replaces** `modelValue` with that code (no tray, no count badge, no limit).
- Consumed by: Task 8 (the new play page, in single mode).

This task has no new automated test of its own — it's a refactor that must keep the existing `e2e/tests/initial-hand.spec.ts` and `e2e/tests/games-new.spec.ts` passing unchanged (same testids, same behavior). The verification step IS running those two specs.

- [ ] **Step 1: Generalize `GameCardTile`'s testid**

Replace the contents of `frontend/app/components/game/card-tile.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { parseCard } from '~/utils/cards'

const props = withDefaults(defineProps<{
  code: string
  count?: number
  selected?: boolean
  atLimit?: boolean
  variant?: 'grid' | 'tray'
  testIdPrefix?: string
}>(), {
  count: 0,
  selected: false,
  atLimit: false,
  variant: 'grid',
  testIdPrefix: 'hand-card'
})

defineEmits<{ click: [] }>()

const card = computed(() => parseCard(props.code))
</script>

<template>
  <button
    type="button"
    class="flex flex-col items-center justify-center gap-0.5 rounded-md border font-body font-bold"
    :class="[
      variant === 'grid' ? 'relative h-[62px]' : 'h-[48px] w-[34px] shrink-0',
      selected ? 'border-ink bg-primary' : 'border-ink/15 bg-white',
      atLimit ? 'cursor-default opacity-30' : 'cursor-pointer'
    ]"
    :disabled="atLimit && variant === 'grid'"
    :data-testid="`${testIdPrefix}-${variant}-${code}`"
    @click="$emit('click')"
  >
    <span class="text-[15px] leading-none" :class="card.isRed ? 'text-card-red' : 'text-card-black'">{{ card.label }}</span>
    <span class="text-[15px] leading-none" :class="card.isJoker ? 'text-ink-deep' : (card.isRed ? 'text-card-red' : 'text-card-black')">{{ card.suitSymbol }}</span>
    <div
      v-if="variant === 'grid' && count > 0"
      class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-pill bg-ink px-1"
    >
      <span class="text-[9px] font-extrabold text-primary">{{ count }}</span>
    </div>
  </button>
</template>
```

The only change from the current file: the `testIdPrefix` prop (default `'hand-card'`) and the testid template now interpolates it instead of the literal `hand-card`. With the default, `` `${testIdPrefix}-${variant}-${code}` `` produces exactly `hand-card-${variant}-${code}` — byte-identical to today.

- [ ] **Step 2: Create `GameCardPicker`**

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { maxCopies, RANKS, SUITS } from '~/utils/cards'

const props = withDefaults(defineProps<{
  modelValue: string | string[]
  multiple?: boolean
  decks?: number
  testIdPrefix?: string
}>(), {
  multiple: false,
  decks: 1,
  testIdPrefix: 'card-picker'
})

const emit = defineEmits<{ 'update:modelValue': [value: string | string[]] }>()

const suitTabs = [...SUITS, { key: 'W' as const, symbol: '★', red: false }]
const pickerSuit = ref<'H' | 'D' | 'C' | 'S' | 'W'>('H')

const pickerCodes = computed(() =>
  pickerSuit.value === 'W' ? ['W'] : RANKS.map((rank) => rank + pickerSuit.value)
)

const multiValue = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []))
const singleValue = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : ''))

function countOf(code: string) {
  return multiValue.value.filter((c) => c === code).length
}

function selectGridCard(code: string) {
  if (props.multiple) {
    if (countOf(code) >= maxCopies(code, props.decks)) return
    emit('update:modelValue', [...multiValue.value, code])
  } else {
    emit('update:modelValue', code)
  }
}

function removeFromTray(code: string) {
  const idx = multiValue.value.indexOf(code)
  if (idx === -1) return
  const next = [...multiValue.value]
  next.splice(idx, 1)
  emit('update:modelValue', next)
}

const deckLimitNote = computed(() =>
  `Máx. ${props.decks} cóp./carta · ${props.decks * 2} coringões (${props.decks} baralho${props.decks > 1 ? 's' : ''})`
)

const tileTestIdPrefix = computed(() => `${props.testIdPrefix}-card`)
</script>

<template>
  <div>
    <div class="flex gap-1.5">
      <button
        v-for="tab in suitTabs"
        :key="tab.key"
        type="button"
        class="flex-1 rounded-md border py-2.5 text-center"
        :class="pickerSuit === tab.key ? 'border-ink bg-ink' : 'border-ink/15 bg-white'"
        :data-testid="`${testIdPrefix}-suit-tab-${tab.key}`"
        @click="pickerSuit = tab.key"
      >
        <span
          class="text-[16px] font-bold"
          :class="pickerSuit === tab.key
            ? (tab.key === 'W' ? 'text-primary' : tab.red ? 'text-[#ff8a8e]' : 'text-white')
            : (tab.red ? 'text-card-red' : 'text-card-black')"
        >{{ tab.symbol }}</span>
      </button>
    </div>

    <p v-if="multiple" class="mb-2.5 mt-3 text-right font-body text-[10px] text-mute" :data-testid="`${testIdPrefix}-deck-limit-note`">{{ deckLimitNote }}</p>

    <div class="mt-3 grid grid-cols-4 gap-2">
      <GameCardTile
        v-for="code in pickerCodes"
        :key="code"
        :code="code"
        :count="multiple ? countOf(code) : 0"
        :selected="multiple ? countOf(code) > 0 : singleValue === code"
        :at-limit="multiple ? countOf(code) >= maxCopies(code, decks) : false"
        variant="grid"
        :test-id-prefix="tileTestIdPrefix"
        @click="selectGridCard(code)"
      />
    </div>

    <div v-if="multiple" class="mt-3 flex min-h-[50px] items-center gap-1 overflow-x-auto">
      <span v-if="multiValue.length === 0" class="font-body text-[12px] text-mute">Nenhuma carta ainda</span>
      <GameCardTile
        v-for="(code, index) in multiValue"
        :key="`${code}-${index}`"
        :code="code"
        variant="tray"
        :test-id-prefix="tileTestIdPrefix"
        @click="removeFromTray(code)"
      />
    </div>
  </div>
</template>
```

Save as `frontend/app/components/game/card-picker.vue`. With `testIdPrefix="hand"` (Step 3 below), `tileTestIdPrefix` is `"hand-card"`, so `GameCardTile`'s testid becomes `hand-card-grid-${code}`/`hand-card-tray-${code}` — identical to today.

- [ ] **Step 3: Refactor `initial-hand.vue` to use `GameCardPicker`**

Replace the contents of `frontend/app/pages/games/[id]/initial-hand.vue`:

```vue
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

interface PlayerSummary { id: string, seatIndex: number, name: string }
interface GameSummary { id: string, decks: number, targetScore: number, players: PlayerSummary[] }

const route = useRoute()
const gameId = route.params.id as string

const game = ref<GameSummary | null>(null)
const loadError = ref<string | null>(null)

onMounted(async () => {
  try {
    game.value = await $fetch<GameSummary>(`/api/games/${gameId}`)
  } catch {
    loadError.value = 'Não foi possível carregar a partida.'
  }
})

const me = computed(() => game.value?.players.find((p) => p.seatIndex === 0) ?? null)

const handCards = ref<string[]>([])

const handCount = computed(() => handCards.value.length)
const handComplete = computed(() => handCount.value === 13)

const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitted = ref(false)

async function confirmHand() {
  if (!me.value || !handComplete.value) return
  submitError.value = null
  submitting.value = true
  try {
    await $fetch(`/api/players/${me.value.id}/hand`, {
      method: 'POST',
      body: { cards: handCards.value }
    })
    submitted.value = true
  } catch (err) {
    submitError.value = (err as { data?: { message?: string } })?.data?.message
      ?? 'Não foi possível registrar a mão.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white">
    <header class="flex items-center gap-3 border-b border-ink/15 px-6 py-4">
      <button type="button" data-testid="initial-hand-back" @click="navigateTo('/games/new')">
        <Icon name="mdi:arrow-left" class="text-[20px] text-ink" />
      </button>
      <div>
        <h1 class="font-display font-black text-[17px] text-ink" data-testid="initial-hand-title">Registrar mão</h1>
        <p class="font-body text-[11px] text-mute">Toque para adicionar — toque de novo para repetir a carta</p>
      </div>
    </header>

    <p v-if="loadError" class="px-6 py-4 font-body text-[14px] text-negative" data-testid="initial-hand-load-error">{{ loadError }}</p>

    <template v-else-if="submitted">
      <div class="flex flex-1 items-center justify-center px-6 text-center">
        <p class="font-body text-[16px] text-ink" data-testid="initial-hand-success">
          Mão registrada! Aguardando o início da partida.
        </p>
      </div>
    </template>

    <template v-else-if="game">
      <div class="flex-1 overflow-y-auto px-6 py-4">
        <GameCardPicker
          multiple
          test-id-prefix="hand"
          :decks="game.decks"
          :model-value="handCards"
          @update:model-value="(value) => (handCards = value as string[])"
        />
      </div>

      <div class="border-t border-ink/15 bg-canvas-soft px-6 py-3">
        <div class="flex items-baseline justify-between">
          <span class="font-body text-[12px] font-semibold text-ink">Minha mão</span>
          <span
            class="font-body text-[12px] font-semibold"
            :class="handComplete ? 'text-positive' : 'text-mute'"
            data-testid="hand-count"
          >{{ handCount }} / 13 · toque p/ remover</span>
        </div>
      </div>

      <p v-if="submitError" class="px-6 py-2 font-body text-[14px] text-negative" data-testid="initial-hand-error">{{ submitError }}</p>

      <footer class="border-t border-ink/15 px-6 py-3">
        <BaseButton
          variant="primary"
          class="w-full justify-center"
          data-testid="confirm-hand"
          :disabled="!handComplete || submitting"
          @click="confirmHand"
        >
          Confirmar e começar
          <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
        </BaseButton>
      </footer>
    </template>
  </div>
</template>
```

The removed pieces (`pickerSuit`, `pickerCodes`, `suitTabs`, `countOf`, `addCard`, `removeOne`, `deckLimitNote`, the inline suit-tabs/grid/tray markup) now live inside `GameCardPicker`. `hand-suit-tab-*`, `hand-deck-limit-note`, `hand-card-grid-*`, `hand-card-tray-*`, and `hand-count` all keep producing the exact same testid strings as before.

- [ ] **Step 4: Run the existing e2e suite to confirm no regression**

Run: `cd e2e && pnpm test`
Expected: both `games-new.spec.ts` and `initial-hand.spec.ts` PASS, unchanged.

- [ ] **Step 5: Commit**

```bash
cd /home/kauan/Desktop/canastra-ai
git add frontend/app/components/game/card-tile.vue frontend/app/components/game/card-picker.vue frontend/app/pages/games/\[id\]/initial-hand.vue
git commit -m "refactor: extract GameCardPicker from the hand registration screen"
```

---

## Task 7: Write the failing e2e test for the play-registration screen

**Files:**
- Create: `e2e/tests/register-play.spec.ts`

**Interfaces:**
- Consumes (testids the test asserts on — must be produced by Task 8): `play-turn-name`, `play-draw-monte`, `play-lower-no`, `register-play`, plus `GameCardPicker`'s `${testIdPrefix}-suit-tab-*`/`${testIdPrefix}-card-grid-*` with prefixes `drawn-code`/`discarded-code` (from Task 6, already merged).

- [ ] **Step 1: Write the test**

Create `e2e/tests/register-play.spec.ts`:

```ts
import { expect, test, type Page } from '@playwright/test'

async function createGameWithRegisteredHand(page: Page): Promise<string> {
  await page.goto('/games/new', { waitUntil: 'networkidle' })
  const nameInputs = page.getByTestId('player-name-input')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')
  await page.getByTestId('decks-option-2').click()
  await page.getByTestId('submit-new-game').click()
  await page.waitForURL(/\/games\/([\w-]+)\/initial-hand$/)

  const cards = ['AH', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS']
  for (const code of cards) {
    const suit = code.slice(-1)
    await page.getByTestId(`hand-suit-tab-${suit}`).click()
    await page.getByTestId(`hand-card-grid-${code}`).click()
  }
  await page.getByTestId('confirm-hand').click()
  await expect(page.getByTestId('initial-hand-success')).toBeVisible()

  const match = page.url().match(/\/games\/([\w-]+)\/initial-hand$/)
  if (!match) throw new Error('game id not found in URL')

  return match[1]
}

test('registra uma jogada completa e passa a vez', async ({ page }) => {
  let gameId = ''

  await test.step('cria a partida e registra a mão inicial', async () => {
    gameId = await createGameWithRegisteredHand(page)
  })

  await test.step('acessa a tela de registrar jogada', async () => {
    await page.goto(`/games/${gameId}/play`, { waitUntil: 'networkidle' })
    await expect(page.getByTestId('play-turn-name')).toHaveText('Ana')
  })

  await test.step('compra do monte e informa a carta comprada', async () => {
    await page.getByTestId('play-draw-monte').click()
    await page.getByTestId('drawn-code-suit-tab-D').click()
    await page.getByTestId('drawn-code-card-grid-AD').click()
  })

  await test.step('não baixa nada na mesa', async () => {
    await page.getByTestId('play-lower-no').click()
  })

  await test.step('descarta a carta comprada', async () => {
    await page.getByTestId('discarded-code-suit-tab-D').click()
    await page.getByTestId('discarded-code-card-grid-AD').click()
  })

  await test.step('registra a jogada e passa a vez', async () => {
    await page.getByTestId('register-play').click()
    await expect(page.getByTestId('play-turn-name')).toHaveText('Bruno')
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd e2e && pnpm test register-play.spec.ts`
Expected: FAIL — `/games/[id]/play` doesn't exist yet (404 or missing testids).

- [ ] **Step 3: Commit**

```bash
cd e2e
git add tests/register-play.spec.ts
git commit -m "test: add e2e coverage for registering a play"
```

---

## Task 8: Server proxy route + the `play.vue` page

**Files:**
- Create: `frontend/server/api/games/[id]/plays.post.ts`
- Create: `frontend/app/pages/games/[id]/play.vue`

**Interfaces:**
- Consumes: `GameCardPicker` (Task 6), `GET /api/games/:id`'s `turnIndex`/`discardTop`/`players[].handCount` fields (Task 5), `POST /api/games/:id/plays` (Task 4).
- Produces: the testids asserted by Task 7's e2e test.

- [ ] **Step 1: Create the proxy route**

Create `frontend/server/api/games/[id]/plays.post.ts`:

```ts
export default defineEventHandler(async (event) => {
  const id = getRouterParam(event, 'id')
  const body = await readBody(event)

  try {
    return await canastraClient()(`/games/${id}/plays`, { method: 'POST', body })
  } catch (error) {
    if (error instanceof Error && 'statusCode' in error) {
      const fetchError = error as Error & { statusCode: number, data?: unknown }
      throw createError({ statusCode: fetchError.statusCode, data: fetchError.data })
    }

    throw error
  }
})
```

- [ ] **Step 2: Create the page**

Create `frontend/app/pages/games/[id]/play.vue`:

```vue
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

interface PlayerSummary { id: string, seatIndex: number, name: string, handCount: number }
interface GameSummary { id: string, decks: number, targetScore: number, turnIndex: number, discardTop: string | null, players: PlayerSummary[] }

const route = useRoute()
const gameId = route.params.id as string

const game = ref<GameSummary | null>(null)
const loadError = ref<string | null>(null)

async function loadGame() {
  try {
    game.value = await $fetch<GameSummary>(`/api/games/${gameId}`)
  } catch {
    loadError.value = 'Não foi possível carregar a partida.'
  }
}

onMounted(loadGame)

const currentPlayer = computed(() => {
  if (!game.value) return null
  const players = game.value.players
  return players[game.value.turnIndex % players.length] ?? null
})

const isTracked = computed(() => currentPlayer.value?.seatIndex === 0)

const drewFrom = ref<'monte' | 'lixo' | null>(null)
const drawnCode = ref('')
const lowered = ref<boolean | null>(null)
const loweredCount = ref(0)
const discardedCode = ref('')

const remaining = computed(() => {
  if (!currentPlayer.value) return 0
  return currentPlayer.value.handCount + 1 - loweredCount.value
})

const needsDiscard = computed(() => remaining.value > 0)
const needsDrawnCode = computed(() => drewFrom.value === 'monte' && isTracked.value)

const submitting = ref(false)
const submitError = ref<string | null>(null)

function resetForm() {
  drewFrom.value = null
  drawnCode.value = ''
  lowered.value = null
  loweredCount.value = 0
  discardedCode.value = ''
}

async function registerPlay() {
  if (!currentPlayer.value || !drewFrom.value) return
  submitError.value = null
  submitting.value = true
  try {
    await $fetch(`/api/games/${gameId}/plays`, {
      method: 'POST',
      body: {
        playerId: currentPlayer.value.id,
        drewFrom: drewFrom.value,
        drawnCode: needsDrawnCode.value ? drawnCode.value : null,
        discardedCode: needsDiscard.value ? discardedCode.value : null,
        loweredCount: loweredCount.value
      }
    })
    resetForm()
    await loadGame()
  } catch (err) {
    submitError.value = (err as { data?: { message?: string } })?.data?.message
      ?? 'Não foi possível registrar a jogada.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white">
    <header class="border-b border-ink/15 px-6 py-4">
      <h1 class="font-display font-black text-[17px] text-ink">Registrar jogada</h1>
      <p v-if="currentPlayer" class="font-body text-[13px] text-mute">
        Vez de <span class="font-semibold text-ink" data-testid="play-turn-name">{{ currentPlayer.name }}</span>
      </p>
    </header>

    <p v-if="loadError" class="px-6 py-4 font-body text-[14px] text-negative" data-testid="play-load-error">{{ loadError }}</p>

    <template v-else-if="game && currentPlayer">
      <div class="flex-1 space-y-6 overflow-y-auto px-6 py-4">
        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">1 · De onde comprou</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="drewFrom === 'monte' ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-draw-monte"
              @click="drewFrom = 'monte'"
            >Monte</button>
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="drewFrom === 'lixo' ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-draw-lixo"
              @click="drewFrom = 'lixo'"
            >Pegou o lixo</button>
          </div>
          <GameCardPicker
            v-if="needsDrawnCode"
            test-id-prefix="drawn-code"
            class="mt-3"
            :model-value="drawnCode"
            @update:model-value="(value) => (drawnCode = value as string)"
          />
        </section>

        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">2 · Baixou na mesa</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="lowered === false ? 'bg-ink text-white' : 'border-ink/15 bg-white'"
              data-testid="play-lower-no"
              @click="lowered = false; loweredCount = 0"
            >Não</button>
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="lowered === true ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-lower-yes"
              @click="lowered = true; loweredCount = 1"
            >Sim</button>
          </div>
          <div v-if="lowered" class="mt-3 flex items-center gap-2">
            <button
              type="button"
              class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
              data-testid="play-lower-count-decrement"
              @click="loweredCount = Math.max(1, loweredCount - 1)"
            >−</button>
            <span class="flex-1 rounded-md border border-ink/15 px-4 py-3 text-center font-body font-bold text-[18px] text-ink" data-testid="play-lower-count-value">{{ loweredCount }}</span>
            <button
              type="button"
              class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
              data-testid="play-lower-count-increment"
              @click="loweredCount += 1"
            >+</button>
          </div>
        </section>

        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">3 · Descartou</h2>
          <p v-if="!needsDiscard" class="font-body text-[13px] text-mute" data-testid="play-no-discard-needed">Bateu — sem descarte.</p>
          <GameCardPicker
            v-else
            test-id-prefix="discarded-code"
            :model-value="discardedCode"
            @update:model-value="(value) => (discardedCode = value as string)"
          />
        </section>
      </div>

      <p v-if="submitError" class="px-6 py-2 font-body text-[14px] text-negative" data-testid="play-error">{{ submitError }}</p>

      <footer class="border-t border-ink/15 px-6 py-3">
        <BaseButton
          variant="primary"
          class="w-full justify-center"
          data-testid="register-play"
          :disabled="!drewFrom || (needsDrawnCode && !drawnCode) || (needsDiscard && !discardedCode) || submitting"
          @click="registerPlay"
        >
          Registrar e passar a vez
          <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
        </BaseButton>
      </footer>
    </template>
  </div>
</template>
```

- [ ] **Step 3: Run the e2e test to verify it passes**

Run: `cd e2e && pnpm test register-play.spec.ts`
Expected: PASS.

- [ ] **Step 4: Run the full e2e suite to check for regressions**

Run: `cd e2e && pnpm test`
Expected: all 3 spec files PASS (`games-new.spec.ts`, `initial-hand.spec.ts`, `register-play.spec.ts`).

- [ ] **Step 5: Commit**

```bash
cd /home/kauan/Desktop/canastra-ai
git add frontend/server/api/games/\[id\]/plays.post.ts frontend/app/pages/games/\[id\]/play.vue
git commit -m "feat: implement the play registration screen"
```

---

## Self-Review Notes

- **Spec coverage:** `turn_index`/`hand_count`/`discard_position` columns (Task 1), `plays` log table (Task 2), all 6 new domain exceptions (Task 3), the `RegisterPlay` action covering tracked/untracked draw, lixo pickup with re-anonymization, mandatory-discard-except-bate, turn advancement (Task 4), the `GET` extension (Task 5), `GameCardPicker` extraction with exact testid preservation (Task 6), and the new minimal play screen (Tasks 7–8) — every section of the spec maps to a task.
- **Type consistency:** `RegisterPlayData`'s fields (`playerId`, `drewFrom`, `drawnCode`, `discardedCode`, `loweredCount`) match the frontend page's POST body exactly. `GameDetailData`/`PlayerData`'s new fields (`turnIndex`, `discardTop`, `handCount`) match the `GameSummary`/`PlayerSummary` TypeScript interfaces used in both `play.vue` (Task 8). `GameCardPicker`'s prop names (`modelValue`, `multiple`, `decks`, `testIdPrefix`) are used identically in both `initial-hand.vue` (Task 6) and `play.vue` (Task 8).
- **Out of scope (carried over from the design spec):** recolher o lixo inteiro (só o topo), integração real de "baixar na mesa" com `CreateSequence`/`ExtendSequence`, validação de obriga/canastra para bater, IA, histórico, fim de rodada, placar.

