# Registrar Mão Inicial — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the "Registrar mão" screen so the device's player (seat 0) can select their 13 initial cards (duplicates allowed up to the game's deck count), persist that hand against a real per-game deck of cards in the backend, and see a confirmation once registered.

**Architecture:** Laravel backend models the full physical deck as `cards` rows per game (generated at game creation). Registering a hand claims specific `cards` rows from that game's deck (status `deck` → `hand`), so the deck is the single source of truth for "how many copies of X are left" — no separate copy-count formula in the backend. Nuxt frontend renders the picker screen from the design handoff, calling two new server-proxied endpoints (`GET /api/games/{id}`, `POST /api/players/{id}/hand`).

**Tech Stack:** Laravel 13 + Pest (backend), `spatie/laravel-data`, `lorisleiva/laravel-actions`; Nuxt 4 + Vue 3 + Tailwind, Playwright (`./e2e`).

## Global Constraints

- Backend: always write the Pest test first (RED), then the minimal implementation (GREEN). Pattern: Controller → Action (`handle()`, via `AsAction`, called with `::run()`) → Data (input) → Data (output) → Resource. No `Service` suffix, no `__invoke`.
- Frontend: write the Playwright e2e test first (RED) before implementing the page/component.
- Every interactive/test-relevant frontend element gets a `data-testid`, used by Playwright via `getByTestId`.
- Every Playwright action wrapped in `test.step('...', async () => { ... })`.
- Navigate with `page.goto(url, { waitUntil: 'networkidle' })` in e2e tests (hydration).
- No raw characters/emoji for action/direction icons — use `<Icon name="mdi:...">`. (Suit glyphs `♥♦♣♠` are card-domain content, not action icons — already used as plain characters in `frontend/app/components/base/playing-card.vue`, so the same is fine here.)
- Client never calls the Laravel backend directly — always via Nuxt `server/api/*` routes using `canastraClient()`.
- Git commits: one line, semantic prefix (`feat`, `fix`, `test`, etc.), no body, never mention Claude.
- Card code format: `[RANK][SUIT]` (ranks `A,2-9,T,J,Q,K`, suits `S,H,C,D`) or `W` for the joker (no suit).

---

## Backend

### Task 1: `cards` table, `Card` model, and `Game`/`Player` relations

**Files:**
- Create: `backend/database/migrations/2026_06_20_190000_create_cards_table.php`
- Create: `backend/app/Models/Card.php`
- Modify: `backend/app/Models/Game.php`
- Modify: `backend/app/Models/Player.php`

**Interfaces:**
- Produces: `Card` model with columns `id` (uuid PK), `game_id` (FK), `code` (string), `status` (string, default `deck`), `player_id` (FK nullable). `Game::cards(): HasMany`, `Player::cards(): HasMany`, `Card::game(): BelongsTo`, `Card::player(): BelongsTo`.

This task has no behavior to test on its own (no controller/action yet) — it is infrastructure consumed by Task 2. Verify it via `php artisan migrate` succeeding and `php artisan tinker` is not needed; Task 2's tests exercise it.

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
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->references('id')->on('games');
            $table->string('code');
            $table->string('status')->default('deck');
            $table->foreignUuid('player_id')->nullable()->references('id')->on('players');
            $table->timestamps();

            $table->index(['game_id', 'code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
```

Save as `backend/database/migrations/2026_06_20_190000_create_cards_table.php`.

- [ ] **Step 2: Create the `Card` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'game_id', 'code', 'status', 'player_id'])]
class Card extends Model
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

- [ ] **Step 3: Add the `cards()` relation to `Game`**

In `backend/app/Models/Game.php`, add alongside `players()`:

```php
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
```

(`HasMany` is already imported in this file.)

- [ ] **Step 4: Add the `cards()` relation to `Player`**

In `backend/app/Models/Player.php`, add the import and method:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

```php
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
```

- [ ] **Step 5: Run the migration**

Run: `cd backend && php artisan migrate`
Expected: `cards` table created with no errors.

- [ ] **Step 6: Commit**

```bash
cd backend
git add database/migrations/2026_06_20_190000_create_cards_table.php app/Models/Card.php app/Models/Game.php app/Models/Player.php
git commit -m "feat: add cards table to track each game's deck"
```

---

### Task 2: Generate the full deck when a game is created

**Files:**
- Modify: `backend/tests/Feature/Games/CreateGameTest.php`
- Modify: `backend/app/Actions/Game/CreateGame.php`

**Interfaces:**
- Consumes: `App\Models\Card` (Task 1).
- Produces: `CreateGame::createDeck(): void` — called from `handle()` inside the existing transaction. After `CreateGame::run($data)`, `Card::where('game_id', $game->id)` contains `54 * decks` rows, all `status = 'deck'`, `player_id = null`: per deck-copy, 1 row per `rank+suit` (13 ranks × 4 suits = 52) plus 2 rows with `code = 'W'`.

- [ ] **Step 1: Write the failing tests**

Append to `backend/tests/Feature/Games/CreateGameTest.php` (add `use App\Models\Card;` to the top imports alongside the existing `use App\Models\Game;` / `use App\Models\Player;`):

```php
it('creates a full deck of cards for a 2-deck game', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 2,
        'targetScore' => 3000,
        'players' => ['Ana', 'Bruno'],
    ]);

    $id = $response->json('id');
    $cards = Card::where('game_id', $id)->get();

    expect($cards)->toHaveCount(108);
    expect($cards->where('status', 'deck'))->toHaveCount(108);
    expect($cards->where('player_id', null))->toHaveCount(108);
    expect($cards->where('code', 'W'))->toHaveCount(4);
    expect($cards->where('code', 'AS'))->toHaveCount(2);
});

it('creates a single deck worth of cards for a 1-deck game', function () {
    $response = $this->postJson('/api/games', [
        'decks' => 1,
        'targetScore' => 1500,
        'players' => ['Ana', 'Bruno'],
    ]);

    $id = $response->json('id');

    expect(Card::where('game_id', $id)->count())->toBe(54);
    expect(Card::where('game_id', $id)->where('code', 'W')->count())->toBe(2);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/CreateGameTest.php`
Expected: the two new tests FAIL (expected count `108`/`54`, actual `0`).

- [ ] **Step 3: Implement `createDeck()` in `CreateGame`**

In `backend/app/Actions/Game/CreateGame.php`, add the import:

```php
use App\Models\Card;
```

Update `handle()` to also call `createDeck()` inside the transaction:

```php
    public function handle(CreateGameData $data): GameData
    {
        $this->data = $data;

        DB::transaction(function () {
            $this->createGame();
            $this->batchCreatePlayer();
            $this->createDeck();
        });

        return GameData::from($this->game);
    }
```

Add the new method (after `createPlayer()`):

```php
    public function createDeck(): void
    {
        $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'];
        $suits = ['S', 'H', 'C', 'D'];

        $cards = [];

        for ($deck = 0; $deck < $this->data->decks; $deck++) {
            foreach ($suits as $suit) {
                foreach ($ranks as $rank) {
                    $cards[] = [
                        'id' => (string) Str::uuid(),
                        'game_id' => $this->game->id,
                        'code' => $rank.$suit,
                        'status' => 'deck',
                        'player_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            for ($joker = 0; $joker < 2; $joker++) {
                $cards[] = [
                    'id' => (string) Str::uuid(),
                    'game_id' => $this->game->id,
                    'code' => 'W',
                    'status' => 'deck',
                    'player_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Card::insert($cards);
    }
```

(`Str` is already imported in this file.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/CreateGameTest.php`
Expected: all tests PASS (including the pre-existing ones).

- [ ] **Step 5: Commit**

```bash
cd backend
git add tests/Feature/Games/CreateGameTest.php app/Actions/Game/CreateGame.php
git commit -m "feat: generate the full deck of cards when a game is created"
```

---

### Task 3: `GET /api/games/{game}` — game detail with decks and players

**Files:**
- Create: `backend/tests/Feature/Games/ShowGameTest.php`
- Create: `backend/app/Data/Player/PlayerData.php`
- Create: `backend/app/Data/Game/GameDetailData.php`
- Create: `backend/app/Actions/Game/ShowGame.php`
- Create: `backend/app/Http/Resources/GameDetailResource.php`
- Modify: `backend/app/Http/Controllers/Api/GameController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Produces: `GET /api/games/{game}` → `{ id, decks, targetScore, players: [{ id, seatIndex, name }, ...] }`, players ordered by `seatIndex` ascending. 404 (Laravel default) when the game id doesn't exist.
- Consumed by: Task 8 (frontend page, via `GET /api/games/:id` proxy).

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Games/ShowGameTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/ShowGameTest.php`
Expected: FAIL — route `GET /api/games/{game}` doesn't exist (404 routing error / no route matched).

- [ ] **Step 3: Create `PlayerData`**

Create `backend/app/Data/Player/PlayerData.php`:

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
    ) {}
}
```

- [ ] **Step 4: Create `GameDetailData`**

Create `backend/app/Data/Game/GameDetailData.php`:

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
        /** @var PlayerData[] */
        public array $players,
    ) {}
}
```

- [ ] **Step 5: Create the `ShowGame` action**

Create `backend/app/Actions/Game/ShowGame.php`:

```php
<?php

namespace App\Actions\Game;

use App\Data\Game\GameDetailData;
use App\Data\Player\PlayerData;
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
        return new GameDetailData(
            id: $this->game->id,
            decks: $this->game->decks,
            targetScore: $this->game->target_score,
            players: $this->game->players->map(fn (Player $player) => new PlayerData(
                id: $player->id,
                seatIndex: $player->seat_index,
                name: $player->name,
            ))->all(),
        );
    }
}
```

- [ ] **Step 6: Create `GameDetailResource`**

Create `backend/app/Http/Resources/GameDetailResource.php`:

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
            'players' => $this->players,
        ];
    }
}
```

- [ ] **Step 7: Add `show()` to `GameController`**

In `backend/app/Http/Controllers/Api/GameController.php`, add the imports:

```php
use App\Actions\Game\ShowGame;
use App\Http\Resources\GameDetailResource;
use App\Models\Game;
```

Add the method:

```php
    public function show(Game $game): JsonResponse
    {
        $data = ShowGame::run($game);

        return GameDetailResource::make($data)->response();
    }
```

- [ ] **Step 8: Add the route**

In `backend/routes/api.php`, add below the existing `POST /games` route:

```php
Route::get('/games/{game}', [GameController::class, 'show']);
```

- [ ] **Step 9: Run the test to verify it passes**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Games/ShowGameTest.php`
Expected: both tests PASS.

- [ ] **Step 10: Commit**

```bash
cd backend
git add tests/Feature/Games/ShowGameTest.php app/Data/Player/PlayerData.php app/Data/Game/GameDetailData.php app/Actions/Game/ShowGame.php app/Http/Resources/GameDetailResource.php app/Http/Controllers/Api/GameController.php routes/api.php
git commit -m "feat: add GET /api/games/{game} with decks and players"
```

---

### Task 4: `POST /api/players/{player}/hand` — register a hand against the real deck

**Files:**
- Create: `backend/tests/Feature/Hands/StorePlayerHandTest.php`
- Create: `backend/app/Data/Hand/StoreHandData.php`
- Create: `backend/app/Actions/Hand/StorePlayerHand.php`
- Create: `backend/app/Http/Resources/HandResource.php`
- Create: `backend/app/Http/Controllers/Api/PlayerHandController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `App\Models\Card` (Task 1), `App\Models\Player`.
- Produces: `POST /api/players/{player}/hand` with body `{ cards: string[] }` (exactly 13 codes) → `200 { cards: string[] }` on success; `422` when the array isn't exactly 13 items, contains an invalid code, or asks for more copies of a code than remain in that game's deck.
- Consumed by: Task 8 (frontend page, via `POST /api/players/:id/hand` proxy).

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Hands/StorePlayerHandTest.php`:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Hands/StorePlayerHandTest.php`
Expected: FAIL — route `POST /api/players/{player}/hand` doesn't exist.

- [ ] **Step 3: Create `StoreHandData`**

Create `backend/app/Data/Hand/StoreHandData.php`:

```php
<?php

namespace App\Data\Hand;

use Spatie\LaravelData\Data;

class StoreHandData extends Data
{
    public function __construct(
        public array $cards,
    ) {}

    public static function rules(): array
    {
        return [
            'cards' => ['required', 'array', 'size:13'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
```

- [ ] **Step 4: Create the `StorePlayerHand` action**

Create `backend/app/Actions/Hand/StorePlayerHand.php`:

```php
<?php

namespace App\Actions\Hand;

use App\Data\Hand\StoreHandData;
use App\Models\Card;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePlayerHand
{
    use AsAction;

    /**
     * @return string[]
     */
    public function handle(Player $player, StoreHandData $data): array
    {
        DB::transaction(function () use ($player, $data) {
            $this->releaseCurrentHand($player);
            $this->claimCards($player, $data->cards);
        });

        return $data->cards;
    }

    public function releaseCurrentHand(Player $player): void
    {
        Card::where('player_id', $player->id)
            ->where('status', 'hand')
            ->update(['status' => 'deck', 'player_id' => null]);
    }

    /**
     * @param  string[]  $codes
     */
    public function claimCards(Player $player, array $codes): void
    {
        foreach (array_count_values($codes) as $code => $quantity) {
            $rows = Card::where('game_id', $player->game_id)
                ->where('code', $code)
                ->where('status', 'deck')
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($rows->count() < $quantity) {
                throw ValidationException::withMessages([
                    'cards' => "Não há cópias suficientes de \"{$code}\" disponíveis no baralho desta partida.",
                ]);
            }

            Card::whereIn('id', $rows->pluck('id'))
                ->update(['status' => 'hand', 'player_id' => $player->id]);
        }
    }
}
```

- [ ] **Step 5: Create `HandResource`**

Create `backend/app/Http/Resources/HandResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cards' => $this->resource['cards'],
        ];
    }
}
```

- [ ] **Step 6: Create `PlayerHandController`**

Create `backend/app/Http/Controllers/Api/PlayerHandController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Hand\StorePlayerHand;
use App\Data\Hand\StoreHandData;
use App\Http\Controllers\Controller;
use App\Http\Resources\HandResource;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerHandController extends Controller
{
    public function store(Player $player, StoreHandData $data): JsonResponse
    {
        $cards = StorePlayerHand::run($player, $data);

        return HandResource::make(['cards' => $cards])->response();
    }
}
```

- [ ] **Step 7: Add the route**

In `backend/routes/api.php`, add:

```php
use App\Http\Controllers\Api\PlayerHandController;
```

```php
Route::post('/players/{player}/hand', [PlayerHandController::class, 'store']);
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Hands/StorePlayerHandTest.php`
Expected: all 7 tests PASS.

- [ ] **Step 9: Run the full backend test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS (no regressions in `CreateGameTest`/`ShowGameTest`).

- [ ] **Step 10: Commit**

```bash
cd backend
git add tests/Feature/Hands/StorePlayerHandTest.php app/Data/Hand/StoreHandData.php app/Actions/Hand/StorePlayerHand.php app/Http/Resources/HandResource.php app/Http/Controllers/Api/PlayerHandController.php routes/api.php
git commit -m "feat: register a player's initial hand against the game's deck"
```

---

## Frontend

### Task 5: Write the failing e2e test for the hand registration screen

**Files:**
- Create: `e2e/tests/initial-hand.spec.ts`

**Interfaces:**
- Consumes (testids the test asserts on — must be produced by Tasks 6–8): `initial-hand-title`, `hand-suit-tab-{H,D,C,S,W}`, `hand-card-grid-{code}`, `hand-card-tray-{code}`, `hand-count`, `confirm-hand`, `initial-hand-success`.

- [ ] **Step 1: Write the test**

Create `e2e/tests/initial-hand.spec.ts`:

```ts
import { expect, test, type Page } from '@playwright/test'

async function createGameAndReachHandScreen(page: Page, decks: '1' | '2' | '3' = '2') {
  await page.goto('/games/new', { waitUntil: 'networkidle' })
  const nameInputs = page.getByTestId('player-name-input')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')
  await page.getByTestId(`decks-option-${decks}`).click()
  await page.getByTestId('submit-new-game').click()
  await page.waitForURL(/\/games\/[\w-]+\/initial-hand$/)
  await expect(page.getByTestId('initial-hand-title')).toBeVisible()
}

test('registra as 13 cartas da mão inicial, incluindo duplicatas', async ({ page }) => {
  await test.step('cria uma partida com 2 baralhos e chega na tela de registrar mão', async () => {
    await createGameAndReachHandScreen(page, '2')
  })

  await test.step('seleciona cartas de naipes diferentes', async () => {
    await page.getByTestId('hand-suit-tab-H').click()
    await page.getByTestId('hand-card-grid-AH').click()
    await page.getByTestId('hand-suit-tab-S').click()
    await page.getByTestId('hand-card-grid-2S').click()
    await expect(page.getByTestId('hand-count')).toHaveText('2 / 13 · toque p/ remover')
  })

  await test.step('toca duas vezes na mesma carta para repetir (duplicata permitida com 2 baralhos)', async () => {
    await page.getByTestId('hand-card-grid-2S').click()
    await expect(page.getByTestId('hand-count')).toHaveText('3 / 13 · toque p/ remover')
  })

  await test.step('remove uma carta pela bandeja', async () => {
    await page.getByTestId('hand-card-tray-2S').first().click()
    await expect(page.getByTestId('hand-count')).toHaveText('2 / 13 · toque p/ remover')
    await expect(page.getByTestId('confirm-hand')).toBeDisabled()
  })

  await test.step('completa as 13 cartas', async () => {
    await page.getByTestId('hand-card-grid-2S').click()
    await page.getByTestId('hand-card-grid-3S').click()
    await page.getByTestId('hand-card-grid-4S').click()
    await page.getByTestId('hand-card-grid-5S').click()
    await page.getByTestId('hand-card-grid-6S').click()
    await page.getByTestId('hand-card-grid-7S').click()
    await page.getByTestId('hand-card-grid-8S').click()
    await page.getByTestId('hand-card-grid-9S').click()
    await page.getByTestId('hand-card-grid-TS').click()
    await page.getByTestId('hand-card-grid-JS').click()
    await page.getByTestId('hand-card-grid-QS').click()
    await expect(page.getByTestId('hand-count')).toHaveText('13 / 13 · toque p/ remover')
  })

  await test.step('confirma a mão e vê a tela de sucesso', async () => {
    await page.getByTestId('confirm-hand').click()
    await expect(page.getByTestId('initial-hand-success')).toBeVisible()
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd e2e && pnpm test initial-hand.spec.ts`
Expected: FAIL — `hand-suit-tab-H` etc. don't exist yet (current page is still the stub).

- [ ] **Step 3: Commit**

```bash
cd e2e
git add tests/initial-hand.spec.ts
git commit -m "test: add e2e coverage for registering the initial hand"
```

---

### Task 6: `app/utils/cards.ts` — card parsing helpers

**Files:**
- Create: `frontend/app/utils/cards.ts`

**Interfaces:**
- Produces: `Suit` type, `SUITS` (4 playable suits, `{ key, symbol, red }`), `parseCard(code: string): ParsedCard` (`{ code, rank, suit, isJoker, label, suitSymbol, isRed }`), `maxCopies(code: string, decks: number): number`.
- Consumed by: Task 7 (`GameCardTile.vue`), Task 8 (page).

- [ ] **Step 1: Create the util**

Create `frontend/app/utils/cards.ts`:

```ts
export type Suit = 'S' | 'H' | 'C' | 'D'

export const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'] as const

export const SUITS: { key: Suit, symbol: string, red: boolean }[] = [
  { key: 'H', symbol: '♥', red: true },
  { key: 'D', symbol: '♦', red: true },
  { key: 'C', symbol: '♣', red: false },
  { key: 'S', symbol: '♠', red: false }
]

export interface ParsedCard {
  code: string
  rank: string
  suit: Suit | null
  isJoker: boolean
  label: string
  suitSymbol: string
  isRed: boolean
}

export function parseCard(code: string): ParsedCard {
  if (code === 'W') {
    return { code, rank: 'W', suit: null, isJoker: true, label: 'W', suitSymbol: '★', isRed: false }
  }

  const suit = code.slice(-1) as Suit
  const rawRank = code.slice(0, -1)
  const label = rawRank === 'T' ? '10' : rawRank
  const suitInfo = SUITS.find((s) => s.key === suit)

  return {
    code,
    rank: rawRank,
    suit,
    isJoker: false,
    label,
    suitSymbol: suitInfo?.symbol ?? '',
    isRed: suitInfo?.red ?? false
  }
}

export function maxCopies(code: string, decks: number): number {
  return code === 'W' ? decks * 2 : decks
}
```

This file has no behavioral test of its own — it's exercised end-to-end by Task 5's Playwright test once Tasks 7–8 wire it in. No separate run/verify step here beyond Task 8's final test run.

- [ ] **Step 2: Commit**

```bash
cd frontend
git add app/utils/cards.ts
git commit -m "feat: add card code parsing helpers"
```

---

### Task 7: `GameCardTile.vue` — reusable card tile component

**Files:**
- Create: `frontend/app/components/game/card-tile.vue`

**Interfaces:**
- Consumes: `parseCard` from `~/utils/cards` (Task 6).
- Produces: component `<GameCardTile :code :count :selected :at-limit :variant @click>` (Nuxt auto-imports `components/game/card-tile.vue` as `<GameCardTile>`, following the existing `components/game/player-row.vue` → `<GamePlayerRow>` convention). Renders `data-testid="hand-card-{variant}-{code}"`.
- Consumed by: Task 8 (page).

- [ ] **Step 1: Create the component**

Create `frontend/app/components/game/card-tile.vue`:

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
}>(), {
  count: 0,
  selected: false,
  atLimit: false,
  variant: 'grid'
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
    :data-testid="`hand-card-${variant}-${code}`"
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

No standalone test for this task — it's a presentational component verified visually through Task 8's e2e run.

- [ ] **Step 2: Commit**

```bash
cd frontend
git add app/components/game/card-tile.vue
git commit -m "feat: add reusable card tile component"
```

---

### Task 8: Server proxy routes + the `initial-hand.vue` page

**Files:**
- Create: `frontend/server/api/games/[id].get.ts`
- Create: `frontend/server/api/players/[id]/hand.post.ts`
- Modify: `frontend/app/pages/games/[id]/initial-hand.vue` (replace the stub)

**Interfaces:**
- Consumes: `GameCardTile` (Task 7), `parseCard`/`maxCopies`/`SUITS` from `~/utils/cards` (Task 6), backend endpoints from Tasks 3–4.
- Produces: the testids asserted by Task 5's e2e test (`initial-hand-title`, `hand-suit-tab-*`, `hand-card-grid-*`, `hand-card-tray-*`, `hand-count`, `confirm-hand`, `initial-hand-success`).

- [ ] **Step 1: Create the games detail proxy route**

Create `frontend/server/api/games/[id].get.ts`:

```ts
export default defineEventHandler(async (event) => {
  const id = getRouterParam(event, 'id')

  try {
    return await canastraClient()(`/games/${id}`)
  } catch (error) {
    if (error instanceof Error && 'statusCode' in error) {
      const fetchError = error as Error & { statusCode: number, data?: unknown }
      throw createError({ statusCode: fetchError.statusCode, data: fetchError.data })
    }

    throw error
  }
})
```

- [ ] **Step 2: Create the player hand proxy route**

Create `frontend/server/api/players/[id]/hand.post.ts`:

```ts
export default defineEventHandler(async (event) => {
  const id = getRouterParam(event, 'id')
  const body = await readBody(event)

  try {
    return await canastraClient()(`/players/${id}/hand`, { method: 'POST', body })
  } catch (error) {
    if (error instanceof Error && 'statusCode' in error) {
      const fetchError = error as Error & { statusCode: number, data?: unknown }
      throw createError({ statusCode: fetchError.statusCode, data: fetchError.data })
    }

    throw error
  }
})
```

- [ ] **Step 3: Replace the stub page**

Replace the contents of `frontend/app/pages/games/[id]/initial-hand.vue`:

```vue
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { maxCopies, RANKS, SUITS } from '~/utils/cards'

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

const suitTabs = [...SUITS, { key: 'W' as const, symbol: '★', red: false }]
const pickerSuit = ref<'H' | 'D' | 'C' | 'S' | 'W'>('H')

const pickerCodes = computed(() =>
  pickerSuit.value === 'W' ? ['W'] : RANKS.map((rank) => rank + pickerSuit.value)
)

const handCards = ref<string[]>([])

function countOf(code: string) {
  return handCards.value.filter((c) => c === code).length
}

function addCard(code: string) {
  if (!game.value) return
  if (countOf(code) >= maxCopies(code, game.value.decks)) return
  handCards.value = [...handCards.value, code]
}

function removeOne(code: string) {
  const idx = handCards.value.indexOf(code)
  if (idx === -1) return
  const next = [...handCards.value]
  next.splice(idx, 1)
  handCards.value = next
}

const deckLimitNote = computed(() => {
  if (!game.value) return ''
  const decks = game.value.decks
  return `Máx. ${decks} cóp./carta · ${decks * 2} coringões (${decks} baralho${decks > 1 ? 's' : ''})`
})

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
      <div class="px-6 pt-3">
        <div class="flex gap-1.5">
          <button
            v-for="tab in suitTabs"
            :key="tab.key"
            type="button"
            class="flex-1 rounded-md border py-2.5 text-center"
            :class="pickerSuit === tab.key ? 'border-ink bg-ink' : 'border-ink/15 bg-white'"
            :data-testid="`hand-suit-tab-${tab.key}`"
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
      </div>

      <div class="flex-1 overflow-y-auto px-6 py-4">
        <p class="mb-2.5 text-right font-body text-[10px] text-mute" data-testid="hand-deck-limit-note">{{ deckLimitNote }}</p>
        <div class="grid grid-cols-4 gap-2">
          <GameCardTile
            v-for="code in pickerCodes"
            :key="code"
            :code="code"
            :count="countOf(code)"
            :selected="countOf(code) > 0"
            :at-limit="countOf(code) >= maxCopies(code, game.decks)"
            variant="grid"
            @click="addCard(code)"
          />
        </div>
      </div>

      <div class="border-t border-ink/15 bg-canvas-soft px-6 py-3">
        <div class="mb-2 flex items-baseline justify-between">
          <span class="font-body text-[12px] font-semibold text-ink">Minha mão</span>
          <span
            class="font-body text-[12px] font-semibold"
            :class="handComplete ? 'text-positive' : 'text-mute'"
            data-testid="hand-count"
          >{{ handCount }} / 13 · toque p/ remover</span>
        </div>
        <div class="flex min-h-[50px] items-center gap-1 overflow-x-auto">
          <span v-if="handCount === 0" class="font-body text-[12px] text-mute">Nenhuma carta ainda</span>
          <GameCardTile
            v-for="(code, index) in handCards"
            :key="`${code}-${index}`"
            :code="code"
            variant="tray"
            @click="removeOne(code)"
          />
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

- [ ] **Step 4: Run the e2e test to verify it passes**

Run: `cd e2e && pnpm test initial-hand.spec.ts`
Expected: PASS (all `test.step`s reported in the terminal).

- [ ] **Step 5: Run the full e2e suite to check for regressions**

Run: `cd e2e && pnpm test`
Expected: all tests PASS, including `games-new.spec.ts` (its final assertion on `initial-hand-title` still holds — that testid is now on the screen's persistent header, shown immediately on arrival).

- [ ] **Step 6: Commit**

```bash
cd frontend
git add server/api/games/[id].get.ts server/api/players/[id]/hand.post.ts app/pages/games/[id]/initial-hand.vue
git commit -m "feat: implement the initial hand registration screen"
```

---

## Self-Review Notes

- **Spec coverage:** `cards` table + deck generation (Task 1–2), `GET /api/games/{id}` (Task 3), `POST /api/players/{id}/hand` with deck-backed validation and hand replacement (Task 4), frontend util/component/page (Tasks 6–8), e2e coverage (Task 5), existing `games-new.spec.ts` regression check (Task 8 Step 5) — all spec sections are covered.
- **Type consistency:** `ParsedCard`, `maxCopies`, `GameCardTile` props (`code`, `count`, `selected`, `atLimit`, `variant`), and the page's use of them all match across Tasks 6–8. Backend `GameDetailData`/`PlayerData` field names (`decks`, `targetScore`, `seatIndex`) match what the frontend `GameSummary`/`PlayerSummary` interfaces expect in Task 8.
- **Out of scope (carried over from the design spec):** the "Jogo contínuo" screen, registering hands for seats 1–3, and any API endpoint exposing live deck inventory.
