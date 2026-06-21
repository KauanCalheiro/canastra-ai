# Mesa e Sequências Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Model the table (sequences of cards per team) in the backend: create/extend a sequence and swap a wildcard for a matching real card, with full legality validation (suit/rank consecutiveness, ace-trinca, wildcard limits/coexistence), backed by a new domain-exception hierarchy + global JSON error handler.

**Architecture:** `Card.status` gains a third value `'table'` (alongside `'deck'`/`'hand'`); a new `sequences` table groups table cards by team. A stateless `SequenceLegality` support class derives each card's role (face/wild) from its position and validates wildcard rules; three Actions (`CreateSequence`, `ExtendSequence`, `SwapSequenceCard`) orchestrate claiming real `Card` rows from the team's hand pool and persisting the result. Every business-rule failure throws a specific `DomainException` subclass, rendered consistently by a new global handler in `bootstrap/app.php`.

**Tech Stack:** Laravel 13 + Pest, `spatie/laravel-data`, `lorisleiva/laravel-actions` (existing stack, no new packages).

## Global Constraints

- Backend: always write the Pest test first (RED), then minimal implementation (GREEN).
- Pattern: Controller → Action (`handle()` via `AsAction`, called with `::run()`) → Data (input) → Resource (output). No `Service` suffix, no `__invoke`.
- Rank order is **Ás baixo**: `A,2,3,4,5,6,7,8,9,T,J,Q,K` (indices 0–12). A sequence may never start before index 0 or end after index 12.
- Card code format: `[RANK][SUIT]` (ranks `A,2-9,T,J,Q,K`, suits `S,H,C,D`) or `W` for the joker (no suit).
- Business-rule violations throw a specific `App\Exceptions\DomainException` subclass (never a generic `ValidationException::withMessages()`), rendered by one global handler as `{ error, message, context }` with the exception's `status()` (default 422).
- `ValidationException` (Laravel's own) stays for request-*shape* validation only (`Data::rules()` — types, array sizes, regex).
- Git commits: one line, semantic prefix, no body, never mention Claude.

---

## Task 1: `sequences` table, `Card` table extension, models

**Files:**
- Create: `backend/database/migrations/2026_06_21_120000_create_sequences_table.php`
- Create: `backend/database/migrations/2026_06_21_120001_add_sequence_columns_to_cards_table.php`
- Create: `backend/app/Models/Sequence.php`
- Modify: `backend/app/Models/Card.php`
- Modify: `backend/app/Models/Game.php`

**Interfaces:**
- Produces: `Sequence` model (`id`, `game_id`, `team`, `suit` nullable, `is_ace_trinca` bool, `start_rank` nullable) with `belongsTo(Game)`, `hasMany(Card)`. `Card` gains `sequence_id`, `sequence_position`, `role` columns and `belongsTo(Sequence)`. `Game` gains `sequences(): HasMany`.

No test of its own (pure infrastructure, exercised by Task 5 onward) — verify via migration run only.

- [ ] **Step 1: Write the `sequences` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->references('id')->on('games');
            $table->string('team');
            $table->string('suit')->nullable();
            $table->boolean('is_ace_trinca')->default(false);
            $table->string('start_rank')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
```

Save as `backend/database/migrations/2026_06_21_120000_create_sequences_table.php`.

- [ ] **Step 2: Write the `cards` table extension migration**

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
            $table->foreignUuid('sequence_id')->nullable()->references('id')->on('sequences');
            $table->unsignedInteger('sequence_position')->nullable();
            $table->string('role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['sequence_id', 'sequence_position', 'role']);
        });
    }
};
```

Save as `backend/database/migrations/2026_06_21_120001_add_sequence_columns_to_cards_table.php`.

- [ ] **Step 3: Create the `Sequence` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'game_id', 'team', 'suit', 'is_ace_trinca', 'start_rank'])]
class Sequence extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('sequence_position');
    }
}
```

- [ ] **Step 4: Extend the `Card` model**

In `backend/app/Models/Card.php`, update the `#[Fillable]` attribute and add the new relation:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'game_id', 'code', 'status', 'player_id', 'sequence_id', 'sequence_position', 'role'])]
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

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }
}
```

- [ ] **Step 5: Add `sequences()` to `Game`**

In `backend/app/Models/Game.php`, add (alongside `players()`/`cards()`):

```php
    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }
```

(`HasMany` is already imported in this file.)

- [ ] **Step 6: Run the migrations**

Run: `cd backend && php artisan migrate`
Expected: both new migrations run with no errors.

- [ ] **Step 7: Run the full test suite to confirm no regressions**

Run: `cd backend && ./vendor/bin/pest`
Expected: all 18 existing tests still pass (this task adds no behavior).

- [ ] **Step 8: Commit**

```bash
cd backend
git add database/migrations/2026_06_21_120000_create_sequences_table.php database/migrations/2026_06_21_120001_add_sequence_columns_to_cards_table.php app/Models/Sequence.php app/Models/Card.php app/Models/Game.php
git commit -m "feat: add sequences table and extend cards for table state"
```

---

## Task 2: `DomainException` base + global handler + `StorePlayerHand` retrofit

**Files:**
- Create: `backend/app/Exceptions/DomainException.php`
- Create: `backend/app/Exceptions/InsufficientCardsInPoolException.php`
- Create: `backend/tests/Unit/Exceptions/DomainExceptionTest.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/app/Actions/Hand/StorePlayerHand.php`
- Modify: `backend/tests/Feature/Hands/StorePlayerHandTest.php`

**Interfaces:**
- Produces: `App\Exceptions\DomainException` (abstract — `status(): int` default 422, `errorCode(): string` default snake_case of class basename, `context(): array` default `[]`). `App\Exceptions\InsufficientCardsInPoolException(string $code, int $needed, int $available)` — message in Portuguese, `context()` returns `['code' => ..., 'needed' => ..., 'available' => ...]`.
- Consumed by: Tasks 3–7 (every sequence exception extends `DomainException`; every Action that needs "not enough cards" throws `InsufficientCardsInPoolException`).

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Unit/Exceptions/DomainExceptionTest.php`:

```php
<?php

use App\Exceptions\DomainException;

class ExampleNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('example not found');
    }
}

it('derives the error code from the class name, stripping the Exception suffix', function () {
    $exception = new ExampleNotFoundException();

    expect($exception->errorCode())->toBe('example_not_found');
});

it('defaults status to 422 and context to an empty array', function () {
    $exception = new ExampleNotFoundException();

    expect($exception->status())->toBe(422);
    expect($exception->context())->toBe([]);
});
```

In `backend/tests/Feature/Hands/StorePlayerHandTest.php`, find the test `'rejects a hand that asks for more copies of a card than the deck holds'` and add one assertion line right after `$response->assertStatus(422);`:

```php
    $response->assertStatus(422);
    expect($response->json('error'))->toBe('insufficient_cards_in_pool');
    expect(Card::where('player_id', $player->id)->count())->toBe(0);
```

(The `expect(Card::...)` line already exists — just insert the new `expect($response->json('error'))` line above it.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Exceptions/DomainExceptionTest.php tests/Feature/Hands/StorePlayerHandTest.php`
Expected: FAIL — `DomainException` doesn't exist yet (class not found), and the `StorePlayerHandTest` assertion fails because `$response->json('error')` is `null` (current code throws a generic `ValidationException`, which renders `{"message":..., "errors":{...}}`, no `error` key).

- [ ] **Step 3: Create `DomainException`**

```php
<?php

namespace App\Exceptions;

use Illuminate\Support\Str;

abstract class DomainException extends \Exception
{
    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return Str::snake(Str::beforeLast(class_basename($this), 'Exception'));
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
```

Save as `backend/app/Exceptions/DomainException.php`.

- [ ] **Step 4: Create `InsufficientCardsInPoolException`**

```php
<?php

namespace App\Exceptions;

class InsufficientCardsInPoolException extends DomainException
{
    public function __construct(
        private readonly string $code,
        private readonly int $needed,
        private readonly int $available,
    ) {
        parent::__construct("Não há cópias suficientes de \"{$code}\" disponíveis (precisa de {$needed}, há {$available}).");
    }

    public function context(): array
    {
        return [
            'code' => $this->code,
            'needed' => $this->needed,
            'available' => $this->available,
        ];
    }
}
```

Save as `backend/app/Exceptions/InsufficientCardsInPoolException.php`.

- [ ] **Step 5: Wire the global handler**

Replace the contents of `backend/bootstrap/app.php`:

```php
<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (DomainException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
                'context' => $e->context(),
            ], $e->status());
        });
    })->create();
```

- [ ] **Step 6: Retrofit `StorePlayerHand`**

In `backend/app/Actions/Hand/StorePlayerHand.php`, replace the `use Illuminate\Validation\ValidationException;` import with `use App\Exceptions\InsufficientCardsInPoolException;`, and replace the body of the `if ($rows->count() < $quantity)` block:

```php
            if ($rows->count() < $quantity) {
                throw new InsufficientCardsInPoolException($code, $quantity, $rows->count());
            }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Exceptions/DomainExceptionTest.php tests/Feature/Hands/StorePlayerHandTest.php`
Expected: all tests PASS, including the new assertion in `StorePlayerHandTest`.

- [ ] **Step 8: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all 18 tests PASS.

- [ ] **Step 9: Commit**

```bash
cd backend
git add app/Exceptions/DomainException.php app/Exceptions/InsufficientCardsInPoolException.php tests/Unit/Exceptions/DomainExceptionTest.php bootstrap/app.php app/Actions/Hand/StorePlayerHand.php tests/Feature/Hands/StorePlayerHandTest.php
git commit -m "feat: add domain exception base, global handler, and retrofit StorePlayerHand"
```

---

## Task 3: Sequence exceptions + `SequenceLegality` engine

**Files:**
- Create: `backend/app/Exceptions/PlayerNotInTeamException.php`
- Create: `backend/app/Exceptions/Sequence/SequenceTooShortException.php`
- Create: `backend/app/Exceptions/Sequence/InvalidSequenceCardException.php`
- Create: `backend/app/Exceptions/Sequence/InvalidAceTrincaCardException.php`
- Create: `backend/app/Exceptions/Sequence/MaxWildJokerExceededException.php`
- Create: `backend/app/Exceptions/Sequence/MaxWildTwoExceededException.php`
- Create: `backend/app/Exceptions/Sequence/WildcardCoexistenceException.php`
- Create: `backend/app/Exceptions/Sequence/SequenceRankOutOfBoundsException.php`
- Create: `backend/app/Support/Cards/RankOrder.php`
- Create: `backend/app/Support/Cards/CardCode.php`
- Create: `backend/app/Support/Sequence/SequenceLegality.php`
- Create: `backend/tests/Unit/Support/Sequence/SequenceLegalityTest.php`

**Interfaces:**
- Produces:
  - `App\Support\Cards\RankOrder::RANKS` (array, `['A','2',...,'K']`), `RankOrder::indexOf(string $rank): int`, `RankOrder::rankAt(int $index): ?string`.
  - `App\Support\Cards\CardCode::isJoker(string $code): bool`, `::rank(string $code): string`, `::suit(string $code): ?string`.
  - `App\Support\Sequence\SequenceLegality::expectedRankAt(int $startIndex, int $offset): string` (throws `SequenceRankOutOfBoundsException`).
  - `::resolveRole(string $code, string $expectedRank, string $suit): string` (returns `'face'`|`'wild'`, throws `InvalidSequenceCardException`).
  - `::resolveAceTrincaRole(string $code): string` (returns `'face'`, throws `InvalidAceTrincaCardException`).
  - `::validateWildcardLimits(array $roledCards): void` where `$roledCards` is `array<int, array{code: string, role: string}>` (throws `MaxWildJokerExceededException`/`MaxWildTwoExceededException`/`WildcardCoexistenceException`).
  - `::computeStatus(array $roledCards): string` (returns `'forming'`|`'clean'`|`'dirty'`).
- Consumed by: Tasks 5–7 (`CreateSequence`, `ExtendSequence`, `SwapSequenceCard`).

- [ ] **Step 1: Write the failing unit tests**

Create `backend/tests/Unit/Support/Sequence/SequenceLegalityTest.php`:

```php
<?php

use App\Exceptions\Sequence\InvalidAceTrincaCardException;
use App\Exceptions\Sequence\InvalidSequenceCardException;
use App\Exceptions\Sequence\MaxWildJokerExceededException;
use App\Exceptions\Sequence\MaxWildTwoExceededException;
use App\Exceptions\Sequence\SequenceRankOutOfBoundsException;
use App\Exceptions\Sequence\WildcardCoexistenceException;
use App\Support\Sequence\SequenceLegality;

it('computes the expected rank at a position from the start index', function () {
    expect(SequenceLegality::expectedRankAt(2, 0))->toBe('3'); // index 2 = '3'
    expect(SequenceLegality::expectedRankAt(2, 1))->toBe('4');
});

it('throws when a position would fall before A or after K', function () {
    expect(fn () => SequenceLegality::expectedRankAt(0, -1))->toThrow(SequenceRankOutOfBoundsException::class);
    expect(fn () => SequenceLegality::expectedRankAt(11, 2))->toThrow(SequenceRankOutOfBoundsException::class); // index 11 = Q, +2 = 13 (out of bounds)
});

it('resolves a joker as wild regardless of expected rank/suit', function () {
    expect(SequenceLegality::resolveRole('W', '6', 'H'))->toBe('wild');
});

it('resolves a card matching the expected rank and suit as face', function () {
    expect(SequenceLegality::resolveRole('6H', '6', 'H'))->toBe('face');
});

it('resolves a 2 of any suit placed at a non-2 position as wild', function () {
    expect(SequenceLegality::resolveRole('2D', '6', 'H'))->toBe('wild');
});

it('resolves a 2 of the sequence suit at the 2 position as face', function () {
    expect(SequenceLegality::resolveRole('2H', '2', 'H'))->toBe('face');
});

it('throws when a card does not match the position and is not a valid wildcard', function () {
    expect(fn () => SequenceLegality::resolveRole('9C', '6', 'H'))->toThrow(InvalidSequenceCardException::class);
});

it('resolves an ace as face for an ace-trinca', function () {
    expect(SequenceLegality::resolveAceTrincaRole('AS'))->toBe('face');
});

it('throws when an ace-trinca receives a non-ace or a wildcard', function () {
    expect(fn () => SequenceLegality::resolveAceTrincaRole('2S'))->toThrow(InvalidAceTrincaCardException::class);
    expect(fn () => SequenceLegality::resolveAceTrincaRole('W'))->toThrow(InvalidAceTrincaCardException::class);
});

it('allows at most one wild joker', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => 'W', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(MaxWildJokerExceededException::class);
});

it('allows at most one wild two', function () {
    $roled = [
        ['code' => '2D', 'role' => 'wild'],
        ['code' => '2C', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(MaxWildTwoExceededException::class);
});

it('forbids a wild joker and a wild two coexisting', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => '2D', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(WildcardCoexistenceException::class);
});

it('allows a face two alongside a wild joker', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => '2H', 'role' => 'face'],
    ];
    SequenceLegality::validateWildcardLimits($roled);
})->throwsNoExceptions();

it('computes forming for fewer than 7 cards', function () {
    $roled = array_fill(0, 6, ['code' => '3H', 'role' => 'face']);
    expect(SequenceLegality::computeStatus($roled))->toBe('forming');
});

it('computes clean for 7+ cards with no wild two', function () {
    $roled = array_merge(array_fill(0, 6, ['code' => '3H', 'role' => 'face']), [['code' => 'W', 'role' => 'wild']]);
    expect(SequenceLegality::computeStatus($roled))->toBe('clean');
});

it('computes dirty for 7+ cards with a wild two', function () {
    $roled = array_merge(array_fill(0, 6, ['code' => '3H', 'role' => 'face']), [['code' => '2D', 'role' => 'wild']]);
    expect(SequenceLegality::computeStatus($roled))->toBe('dirty');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Support/Sequence/SequenceLegalityTest.php`
Expected: FAIL — none of the classes exist yet (class not found errors).

- [ ] **Step 3: Create `PlayerNotInTeamException`**

```php
<?php

namespace App\Exceptions;

class PlayerNotInTeamException extends DomainException
{
    public function __construct(
        private readonly string $playerId,
        private readonly string $team,
    ) {
        parent::__construct("O jogador \"{$playerId}\" não pertence à dupla \"{$team}\".");
    }

    public function context(): array
    {
        return ['playerId' => $this->playerId, 'team' => $this->team];
    }
}
```

Save as `backend/app/Exceptions/PlayerNotInTeamException.php`.

- [ ] **Step 4: Create the sequence exceptions**

`backend/app/Exceptions/Sequence/SequenceTooShortException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SequenceTooShortException extends DomainException
{
    public function __construct(private readonly int $count)
    {
        parent::__construct("Uma sequência precisa de no mínimo 3 cartas para ser aberta (recebeu {$count}).");
    }

    public function context(): array
    {
        return ['count' => $this->count];
    }
}
```

`backend/app/Exceptions/Sequence/InvalidSequenceCardException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class InvalidSequenceCardException extends DomainException
{
    public function __construct(
        private readonly string $code,
        private readonly string $expectedRank,
        private readonly string $suit,
    ) {
        parent::__construct("A carta \"{$code}\" não combina com a posição esperada ({$expectedRank} de {$suit}) e não é um curinga válido.");
    }

    public function context(): array
    {
        return ['code' => $this->code, 'expectedRank' => $this->expectedRank, 'suit' => $this->suit];
    }
}
```

`backend/app/Exceptions/Sequence/InvalidAceTrincaCardException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class InvalidAceTrincaCardException extends DomainException
{
    public function __construct(private readonly string $code)
    {
        parent::__construct("A carta \"{$code}\" não é válida numa trinca de ases (precisa ser um Ás, sem curingas).");
    }

    public function context(): array
    {
        return ['code' => $this->code];
    }
}
```

`backend/app/Exceptions/Sequence/MaxWildJokerExceededException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class MaxWildJokerExceededException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Uma sequência só pode ter 1 coringão (Joker) como curinga.');
    }
}
```

`backend/app/Exceptions/Sequence/MaxWildTwoExceededException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class MaxWildTwoExceededException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Uma sequência só pode ter 1 coringuinha (2) usado como curinga.');
    }
}
```

`backend/app/Exceptions/Sequence/WildcardCoexistenceException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class WildcardCoexistenceException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Coringão e coringuinha-curinga não podem coexistir na mesma sequência.');
    }
}
```

`backend/app/Exceptions/Sequence/SequenceRankOutOfBoundsException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SequenceRankOutOfBoundsException extends DomainException
{
    public function __construct(private readonly int $index)
    {
        parent::__construct("A sequência ultrapassaria os limites de A a K (índice calculado: {$index}).");
    }

    public function context(): array
    {
        return ['index' => $this->index];
    }
}
```

- [ ] **Step 5: Create `RankOrder`**

```php
<?php

namespace App\Support\Cards;

class RankOrder
{
    /** @var string[] */
    public const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'];

    public static function indexOf(string $rank): int
    {
        $index = array_search($rank, self::RANKS, true);

        if ($index === false) {
            throw new \InvalidArgumentException("Rank inválido: {$rank}");
        }

        return $index;
    }

    public static function rankAt(int $index): ?string
    {
        return self::RANKS[$index] ?? null;
    }
}
```

Save as `backend/app/Support/Cards/RankOrder.php`.

- [ ] **Step 6: Create `CardCode`**

```php
<?php

namespace App\Support\Cards;

class CardCode
{
    public static function isJoker(string $code): bool
    {
        return $code === 'W';
    }

    public static function rank(string $code): string
    {
        return self::isJoker($code) ? 'W' : substr($code, 0, -1);
    }

    public static function suit(string $code): ?string
    {
        return self::isJoker($code) ? null : substr($code, -1);
    }
}
```

Save as `backend/app/Support/Cards/CardCode.php`.

- [ ] **Step 7: Create `SequenceLegality`**

```php
<?php

namespace App\Support\Sequence;

use App\Exceptions\Sequence\InvalidAceTrincaCardException;
use App\Exceptions\Sequence\InvalidSequenceCardException;
use App\Exceptions\Sequence\MaxWildJokerExceededException;
use App\Exceptions\Sequence\MaxWildTwoExceededException;
use App\Exceptions\Sequence\SequenceRankOutOfBoundsException;
use App\Exceptions\Sequence\WildcardCoexistenceException;
use App\Support\Cards\CardCode;
use App\Support\Cards\RankOrder;

class SequenceLegality
{
    public static function expectedRankAt(int $startIndex, int $offset): string
    {
        $index = $startIndex + $offset;
        $rank = RankOrder::rankAt($index);

        if ($rank === null) {
            throw new SequenceRankOutOfBoundsException($index);
        }

        return $rank;
    }

    public static function resolveRole(string $code, string $expectedRank, string $suit): string
    {
        if (CardCode::isJoker($code)) {
            return 'wild';
        }

        if (CardCode::rank($code) === $expectedRank && CardCode::suit($code) === $suit) {
            return 'face';
        }

        if (CardCode::rank($code) === '2') {
            return 'wild';
        }

        throw new InvalidSequenceCardException($code, $expectedRank, $suit);
    }

    public static function resolveAceTrincaRole(string $code): string
    {
        if (CardCode::isJoker($code) || CardCode::rank($code) !== 'A') {
            throw new InvalidAceTrincaCardException($code);
        }

        return 'face';
    }

    /**
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public static function validateWildcardLimits(array $roledCards): void
    {
        $wildJokers = 0;
        $wildTwos = 0;

        foreach ($roledCards as $entry) {
            if ($entry['role'] !== 'wild') {
                continue;
            }

            if (CardCode::isJoker($entry['code'])) {
                $wildJokers++;
            } else {
                $wildTwos++;
            }
        }

        if ($wildJokers > 1) {
            throw new MaxWildJokerExceededException();
        }

        if ($wildTwos > 1) {
            throw new MaxWildTwoExceededException();
        }

        if ($wildJokers >= 1 && $wildTwos >= 1) {
            throw new WildcardCoexistenceException();
        }
    }

    /**
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public static function computeStatus(array $roledCards): string
    {
        if (count($roledCards) < 7) {
            return 'forming';
        }

        foreach ($roledCards as $entry) {
            if ($entry['role'] === 'wild' && ! CardCode::isJoker($entry['code'])) {
                return 'dirty';
            }
        }

        return 'clean';
    }
}
```

Save as `backend/app/Support/Sequence/SequenceLegality.php`.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Support/Sequence/SequenceLegalityTest.php`
Expected: all tests PASS.

- [ ] **Step 9: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS (18 Feature + new Unit tests), no regressions.

- [ ] **Step 10: Commit**

```bash
cd backend
git add app/Exceptions/PlayerNotInTeamException.php app/Exceptions/Sequence app/Support/Cards app/Support/Sequence tests/Unit/Support/Sequence/SequenceLegalityTest.php
git commit -m "feat: add sequence legality engine and its domain exceptions"
```

---

## Task 4: `Team` and `CardPool` support classes

**Files:**
- Create: `backend/app/Support/Sequence/Team.php`
- Create: `backend/app/Support/Cards/CardPool.php`
- Create: `backend/tests/Unit/Support/Sequence/TeamTest.php`
- Create: `backend/tests/Unit/Support/Cards/CardPoolTest.php`

**Interfaces:**
- Produces:
  - `App\Support\Sequence\Team::of(Player $player): string` (`'A'`|`'B'`, even `seat_index` → `'A'`).
  - `Team::ensure(Player $player, string $team): void` (throws `PlayerNotInTeamException` from Task 3 if mismatched).
  - `Team::playerIds(string $gameId, string $team): string[]`.
  - `App\Support\Cards\CardPool::claimFromHands(array $codes, array $playerIds): \Illuminate\Support\Collection<int, Card>` — locks and returns matching `status='hand'` `Card` rows (does NOT mutate them; caller assigns fields and saves). Throws `InsufficientCardsInPoolException` (from Task 2) if any code is short.
- Consumed by: Tasks 5–7.

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Unit/Support/Sequence/TeamTest.php`:

```php
<?php

use App\Exceptions\PlayerNotInTeamException;
use App\Models\Game;
use App\Models\Player;
use App\Support\Sequence\Team;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

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
```

Create `backend/tests/Unit/Support/Cards/CardPoolTest.php`:

```php
<?php

use App\Exceptions\InsufficientCardsInPoolException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Support\Cards\CardPool;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Support/Sequence/TeamTest.php tests/Unit/Support/Cards/CardPoolTest.php`
Expected: FAIL — `Team` and `CardPool` classes don't exist yet.

- [ ] **Step 3: Create `Team`**

```php
<?php

namespace App\Support\Sequence;

use App\Exceptions\PlayerNotInTeamException;
use App\Models\Player;

class Team
{
    public static function of(Player $player): string
    {
        return $player->seat_index % 2 === 0 ? 'A' : 'B';
    }

    public static function ensure(Player $player, string $team): void
    {
        if (self::of($player) !== $team) {
            throw new PlayerNotInTeamException($player->id, $team);
        }
    }

    /**
     * @return string[]
     */
    public static function playerIds(string $gameId, string $team): array
    {
        return Player::where('game_id', $gameId)
            ->get()
            ->filter(fn (Player $player) => self::of($player) === $team)
            ->pluck('id')
            ->all();
    }
}
```

Save as `backend/app/Support/Sequence/Team.php`.

- [ ] **Step 4: Create `CardPool`**

```php
<?php

namespace App\Support\Cards;

use App\Exceptions\InsufficientCardsInPoolException;
use App\Models\Card;
use Illuminate\Support\Collection;

class CardPool
{
    /**
     * @param  string[]  $codes
     * @param  string[]  $playerIds
     * @return Collection<int, Card>
     */
    public static function claimFromHands(array $codes, array $playerIds): Collection
    {
        $claimed = collect();

        foreach (array_count_values($codes) as $code => $quantity) {
            $rows = Card::where('code', $code)
                ->where('status', 'hand')
                ->whereIn('player_id', $playerIds)
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($rows->count() < $quantity) {
                throw new InsufficientCardsInPoolException($code, $quantity, $rows->count());
            }

            $claimed = $claimed->merge($rows);
        }

        return $claimed;
    }
}
```

Save as `backend/app/Support/Cards/CardPool.php`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Unit/Support/Sequence/TeamTest.php tests/Unit/Support/Cards/CardPoolTest.php`
Expected: all tests PASS.

- [ ] **Step 6: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 7: Commit**

```bash
cd backend
git add app/Support/Sequence/Team.php app/Support/Cards/CardPool.php tests/Unit/Support/Sequence/TeamTest.php tests/Unit/Support/Cards/CardPoolTest.php
git commit -m "feat: add Team and CardPool support classes for sequence claiming"
```

---

## Task 5: `POST /api/games/{game}/sequences` — create

**Files:**
- Create: `backend/tests/Feature/Sequences/CreateSequenceTest.php`
- Create: `backend/app/Data/Sequence/CreateSequenceData.php`
- Create: `backend/app/Actions/Sequence/CreateSequence.php`
- Create: `backend/app/Http/Resources/SequenceResource.php`
- Create: `backend/app/Http/Controllers/Api/SequenceController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `Team::of/ensure/playerIds`, `CardPool::claimFromHands`, `SequenceLegality::*` (Tasks 3–4).
- Produces: `CreateSequence::run(Game $game, CreateSequenceData $data): Sequence` (used directly by Task 5's controller; `SequenceResource` and `SequenceController` are reused/extended by Tasks 6–7, which add `extend`/`swap` methods to the same controller).

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Sequences/CreateSequenceTest.php`:

```php
<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Str;

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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/CreateSequenceTest.php`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Create `CreateSequenceData`**

```php
<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class CreateSequenceData extends Data
{
    public function __construct(
        public string $playerId,
        public ?string $suit,
        public ?string $startRank,
        public bool $acesTrinca,
        public array $cards,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'suit' => ['nullable', 'string', 'in:S,H,C,D'],
            'startRank' => ['nullable', 'string', 'in:A,2,3,4,5,6,7,8,9,T,J,Q,K'],
            'acesTrinca' => ['boolean'],
            'cards' => ['required', 'array'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
```

Save as `backend/app/Data/Sequence/CreateSequenceData.php`.

- [ ] **Step 4: Create the `CreateSequence` action**

```php
<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\CreateSequenceData;
use App\Exceptions\Sequence\SequenceTooShortException;
use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSequence
{
    use AsAction;

    public Game $game;

    public Player $player;

    public string $team;

    public Sequence $sequence;

    public function handle(Game $game, CreateSequenceData $data): Sequence
    {
        $this->game = $game;
        $this->player = Player::where('game_id', $game->id)->findOrFail($data->playerId);
        $this->team = Team::of($this->player);

        if (count($data->cards) < 3) {
            throw new SequenceTooShortException(count($data->cards));
        }

        $roledCards = $data->acesTrinca
            ? $this->resolveAceTrincaRoles($data->cards)
            : $this->resolveNormalRoles($data->cards, $data->suit, $data->startRank);

        SequenceLegality::validateWildcardLimits($roledCards);

        return DB::transaction(function () use ($data, $roledCards) {
            $playerIds = Team::playerIds($this->game->id, $this->team);
            $claimed = CardPool::claimFromHands($data->cards, $playerIds);

            $this->sequence = Sequence::create([
                'id' => (string) Str::uuid(),
                'game_id' => $this->game->id,
                'team' => $this->team,
                'suit' => $data->acesTrinca ? null : $data->suit,
                'is_ace_trinca' => $data->acesTrinca,
                'start_rank' => $data->acesTrinca ? null : $data->startRank,
            ]);

            $this->assignPositions($claimed, $roledCards);

            return $this->sequence;
        });
    }

    /**
     * @param  string[]  $codes
     * @return array<int, array{code: string, role: string}>
     */
    public function resolveAceTrincaRoles(array $codes): array
    {
        return array_map(
            fn (string $code) => ['code' => $code, 'role' => SequenceLegality::resolveAceTrincaRole($code)],
            $codes
        );
    }

    /**
     * @param  string[]  $codes
     * @return array<int, array{code: string, role: string}>
     */
    public function resolveNormalRoles(array $codes, string $suit, string $startRank): array
    {
        $startIndex = RankOrder::indexOf($startRank);
        $roled = [];

        foreach ($codes as $offset => $code) {
            $expectedRank = SequenceLegality::expectedRankAt($startIndex, $offset);
            $roled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $suit)];
        }

        return $roled;
    }

    /**
     * @param  Collection<int, Card>  $claimed
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public function assignPositions(Collection $claimed, array $roledCards): void
    {
        $byCode = $claimed->groupBy('code')->map(fn ($group) => $group->values());
        $pointers = [];

        foreach ($roledCards as $position => $entry) {
            $code = $entry['code'];
            $pointer = $pointers[$code] ?? 0;
            $pointers[$code] = $pointer + 1;

            $byCode[$code][$pointer]->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $position,
                'role' => $entry['role'],
            ]);
        }
    }
}
```

Save as `backend/app/Actions/Sequence/CreateSequence.php`.

- [ ] **Step 5: Create `SequenceResource`**

```php
<?php

namespace App\Http\Resources;

use App\Support\Sequence\SequenceLegality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sequence */
class SequenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cards = $this->cards->map(fn ($card) => ['code' => $card->code, 'role' => $card->role])->all();

        return [
            'id' => $this->id,
            'team' => $this->team,
            'status' => SequenceLegality::computeStatus($cards),
            'cards' => $cards,
        ];
    }
}
```

Save as `backend/app/Http/Resources/SequenceResource.php`.

- [ ] **Step 6: Create `SequenceController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sequence\CreateSequence;
use App\Data\Sequence\CreateSequenceData;
use App\Http\Controllers\Controller;
use App\Http\Resources\SequenceResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SequenceController extends Controller
{
    public function store(Game $game, CreateSequenceData $data): JsonResponse
    {
        $sequence = CreateSequence::run($game, $data);

        return SequenceResource::make($sequence->fresh('cards'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
```

Save as `backend/app/Http/Controllers/Api/SequenceController.php`.

(`->fresh('cards')` reloads the sequence with its cards relation freshly ordered by `sequence_position`, since the `Sequence` instance returned by the action was created before its cards were attached.)

- [ ] **Step 7: Add the route**

In `backend/routes/api.php`, add the import and route:

```php
use App\Http\Controllers\Api\SequenceController;
```

```php
Route::post('/games/{game}/sequences', [SequenceController::class, 'store']);
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/CreateSequenceTest.php`
Expected: all tests PASS.

- [ ] **Step 9: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 10: Commit**

```bash
cd backend
git add tests/Feature/Sequences/CreateSequenceTest.php app/Data/Sequence/CreateSequenceData.php app/Actions/Sequence/CreateSequence.php app/Http/Resources/SequenceResource.php app/Http/Controllers/Api/SequenceController.php routes/api.php
git commit -m "feat: add POST /api/games/{game}/sequences to create sequences"
```

---

## Task 6: `POST /api/sequences/{sequence}/cards` — extend

**Files:**
- Create: `backend/tests/Feature/Sequences/ExtendSequenceTest.php`
- Create: `backend/app/Data/Sequence/ExtendSequenceData.php`
- Create: `backend/app/Actions/Sequence/ExtendSequence.php`
- Modify: `backend/app/Http/Controllers/Api/SequenceController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: everything from Task 5 (`SequenceLegality`, `Team`, `CardPool`, `RankOrder`, `SequenceResource`).
- Produces: `ExtendSequence::run(Sequence $sequence, ExtendSequenceData $data): Sequence`; `SequenceController::extend(Sequence $sequence, ExtendSequenceData $data)`.

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Sequences/ExtendSequenceTest.php`:

```php
<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use Illuminate\Support\Str;

function extendMakeHandCard(string $gameId, string $playerId, string $code): Card
{
    return Card::create([
        'id' => (string) Str::uuid(),
        'game_id' => $gameId,
        'code' => $code,
        'status' => 'hand',
        'player_id' => $playerId,
    ]);
}

/**
 * @return array{0: Game, 1: Player[], 2: Sequence}
 */
function makeOpenSequence(array $cards, string $suit, string $startRank, int $playerCount = 2, bool $acesTrinca = false): array
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

    foreach ($cards as $code) {
        extendMakeHandCard($game->id, $players[0]->id, $code);
    }

    $response = test()->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $players[0]->id,
        'suit' => $acesTrinca ? null : $suit,
        'startRank' => $acesTrinca ? null : $startRank,
        'acesTrinca' => $acesTrinca,
        'cards' => $cards,
    ]);

    $sequence = Sequence::find($response->json('id'));

    return [$game, $players, $sequence];
}

it('extends a sequence after, continuing the position count and status', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', '5H'], 'H', '3');
    extendMakeHandCard($game->id, $players[0]->id, '6H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['6H'],
        'direction' => 'after',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toBe([
        ['code' => '3H', 'role' => 'face'],
        ['code' => '4H', 'role' => 'face'],
        ['code' => '5H', 'role' => 'face'],
        ['code' => '6H', 'role' => 'face'],
    ]);
});

it('extends a sequence before, shifting existing positions and the start rank', function () {
    [$game, $players, $sequence] = makeOpenSequence(['4H', '5H', '6H'], 'H', '4');
    extendMakeHandCard($game->id, $players[0]->id, '3H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['3H'],
        'direction' => 'before',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toBe([
        ['code' => '3H', 'role' => 'face'],
        ['code' => '4H', 'role' => 'face'],
        ['code' => '5H', 'role' => 'face'],
        ['code' => '6H', 'role' => 'face'],
    ]);
    expect($sequence->fresh()->start_rank)->toBe('3');
});

it('extends an ace trinca, ignoring direction', function () {
    [$game, $players, $sequence] = makeOpenSequence(['AS', 'AH', 'AD'], '', '', acesTrinca: true);
    extendMakeHandCard($game->id, $players[0]->id, 'AC');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['AC'],
        'direction' => 'before',
    ]);

    $response->assertOk();
    expect($response->json('cards'))->toHaveCount(4);
    expect(collect($response->json('cards'))->pluck('role')->unique()->all())->toBe(['face']);
});

it('rejects an extension that would exceed the wild joker limit for the whole sequence', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', 'W'], 'H', '3');
    extendMakeHandCard($game->id, $players[0]->id, 'W');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['W'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('max_wild_joker_exceeded');
});

it('rejects an extension that would pass K', function () {
    [$game, $players, $sequence] = makeOpenSequence(['JH', 'QH', 'KH'], 'H', 'J');
    extendMakeHandCard($game->id, $players[0]->id, 'AH');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $players[0]->id,
        'cards' => ['AH'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('sequence_rank_out_of_bounds');
});

it('rejects a player from a different team than the sequence', function () {
    [$game, $players, $sequence] = makeOpenSequence(['3H', '4H', '5H'], 'H', '3', playerCount: 2);
    $outsider = $players[1]; // seat 1, team B
    extendMakeHandCard($game->id, $outsider->id, '6H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards", [
        'playerId' => $outsider->id,
        'cards' => ['6H'],
        'direction' => 'after',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('player_not_in_team');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/ExtendSequenceTest.php`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Create `ExtendSequenceData`**

```php
<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class ExtendSequenceData extends Data
{
    public function __construct(
        public string $playerId,
        public array $cards,
        public string $direction,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'cards' => ['required', 'array', 'min:1'],
            'cards.*' => ['string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
            'direction' => ['required', 'in:before,after'],
        ];
    }
}
```

Save as `backend/app/Data/Sequence/ExtendSequenceData.php`.

- [ ] **Step 4: Create the `ExtendSequence` action**

```php
<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\ExtendSequenceData;
use App\Models\Card;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtendSequence
{
    use AsAction;

    public Sequence $sequence;

    public Player $player;

    public function handle(Sequence $sequence, ExtendSequenceData $data): Sequence
    {
        $this->sequence = $sequence;
        $this->player = Player::where('game_id', $sequence->game_id)->findOrFail($data->playerId);

        Team::ensure($this->player, $sequence->team);

        $existingCards = $sequence->cards;
        $existingRoled = $existingCards->map(fn (Card $card) => ['code' => $card->code, 'role' => $card->role])->all();

        if ($sequence->is_ace_trinca) {
            $newRoled = array_map(
                fn (string $code) => ['code' => $code, 'role' => SequenceLegality::resolveAceTrincaRole($code)],
                $data->cards
            );
            $combined = array_merge($existingRoled, $newRoled);
            $prepend = false;
            $newStartIndex = null;
        } elseif ($data->direction === 'after') {
            $startIndex = RankOrder::indexOf($sequence->start_rank);
            $newRoled = [];
            foreach ($data->cards as $i => $code) {
                $expectedRank = SequenceLegality::expectedRankAt($startIndex, count($existingCards) + $i);
                $newRoled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $sequence->suit)];
            }
            $combined = array_merge($existingRoled, $newRoled);
            $prepend = false;
            $newStartIndex = $startIndex;
        } else {
            $startIndex = RankOrder::indexOf($sequence->start_rank);
            $newStartIndex = $startIndex - count($data->cards);
            $newRoled = [];
            foreach ($data->cards as $i => $code) {
                $expectedRank = SequenceLegality::expectedRankAt($newStartIndex, $i);
                $newRoled[] = ['code' => $code, 'role' => SequenceLegality::resolveRole($code, $expectedRank, $sequence->suit)];
            }
            $combined = array_merge($newRoled, $existingRoled);
            $prepend = true;
        }

        SequenceLegality::validateWildcardLimits($combined);

        return DB::transaction(function () use ($data, $newRoled, $prepend, $newStartIndex, $existingCards) {
            $playerIds = Team::playerIds($this->sequence->game_id, $this->sequence->team);
            $claimed = CardPool::claimFromHands($data->cards, $playerIds);

            if ($prepend) {
                foreach ($existingCards as $card) {
                    $card->update(['sequence_position' => $card->sequence_position + count($data->cards)]);
                }
                $this->sequence->update(['start_rank' => RankOrder::rankAt($newStartIndex)]);
                $offset = 0;
            } else {
                $offset = count($existingCards);
            }

            $this->assignPositions($claimed, $newRoled, $offset);

            return $this->sequence->fresh('cards');
        });
    }

    /**
     * @param  Collection<int, Card>  $claimed
     * @param  array<int, array{code: string, role: string}>  $newRoled
     */
    public function assignPositions(Collection $claimed, array $newRoled, int $offset): void
    {
        $byCode = $claimed->groupBy('code')->map(fn ($group) => $group->values());
        $pointers = [];

        foreach ($newRoled as $i => $entry) {
            $code = $entry['code'];
            $pointer = $pointers[$code] ?? 0;
            $pointers[$code] = $pointer + 1;

            $byCode[$code][$pointer]->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $offset + $i,
                'role' => $entry['role'],
            ]);
        }
    }
}
```

Save as `backend/app/Actions/Sequence/ExtendSequence.php`.

- [ ] **Step 5: Add `extend()` to `SequenceController`**

In `backend/app/Http/Controllers/Api/SequenceController.php`, add the imports:

```php
use App\Actions\Sequence\ExtendSequence;
use App\Data\Sequence\ExtendSequenceData;
use App\Models\Sequence;
```

Add the method:

```php
    public function extend(Sequence $sequence, ExtendSequenceData $data): JsonResponse
    {
        $sequence = ExtendSequence::run($sequence, $data);

        return SequenceResource::make($sequence)->response();
    }
```

- [ ] **Step 6: Add the route**

In `backend/routes/api.php`, add:

```php
Route::post('/sequences/{sequence}/cards', [SequenceController::class, 'extend']);
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/ExtendSequenceTest.php`
Expected: all tests PASS.

- [ ] **Step 8: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 9: Commit**

```bash
cd backend
git add tests/Feature/Sequences/ExtendSequenceTest.php app/Data/Sequence/ExtendSequenceData.php app/Actions/Sequence/ExtendSequence.php app/Http/Controllers/Api/SequenceController.php routes/api.php
git commit -m "feat: add POST /api/sequences/{sequence}/cards to extend sequences"
```

---

## Task 7: `POST /api/sequences/{sequence}/cards/{position}/swap` — swap a wildcard for the real card

**Files:**
- Create: `backend/tests/Feature/Sequences/SwapSequenceCardTest.php`
- Create: `backend/app/Exceptions/Sequence/NothingToSwapException.php`
- Create: `backend/app/Exceptions/Sequence/SwapCardMismatchException.php`
- Create: `backend/app/Data/Sequence/SwapSequenceCardData.php`
- Create: `backend/app/Actions/Sequence/SwapSequenceCard.php`
- Modify: `backend/app/Http/Controllers/Api/SequenceController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: everything from Tasks 5–6.
- Produces: `SwapSequenceCard::run(Sequence $sequence, int $position, SwapSequenceCardData $data): Sequence`; `SequenceController::swap(Sequence $sequence, int $position, SwapSequenceCardData $data)`.

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Sequences/SwapSequenceCardTest.php`:

```php
<?php

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sequence;
use Illuminate\Support\Str;

function swapMakeHandCard(string $gameId, string $playerId, string $code): Card
{
    return Card::create([
        'id' => (string) Str::uuid(),
        'game_id' => $gameId,
        'code' => $code,
        'status' => 'hand',
        'player_id' => $playerId,
    ]);
}

/**
 * @return array{0: Game, 1: Player[], 2: Sequence}
 */
function makeSequenceForSwap(array $cards, string $suit, string $startRank): array
{
    $game = Game::create(['id' => (string) Str::uuid(), 'decks' => 2, 'target_score' => 1000]);
    $players = [
        Player::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'seat_index' => 0, 'name' => 'Ana']),
        Player::create(['id' => (string) Str::uuid(), 'game_id' => $game->id, 'seat_index' => 1, 'name' => 'Bruno']),
    ];

    foreach ($cards as $code) {
        swapMakeHandCard($game->id, $players[0]->id, $code);
    }

    $response = test()->postJson("/api/games/{$game->id}/sequences", [
        'playerId' => $players[0]->id,
        'suit' => $suit,
        'startRank' => $startRank,
        'acesTrinca' => false,
        'cards' => $cards,
    ]);

    return [$game, $players, Sequence::find($response->json('id'))];
}

it('swaps a wild joker for the matching real card, returning the joker to hand', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '5H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5H',
    ]);

    $response->assertOk();
    expect($response->json('cards.2'))->toBe(['code' => '5H', 'role' => 'face']);

    $freed = Card::where('code', 'W')->where('game_id', $game->id)->first();
    expect($freed->status)->toBe('hand');
    expect($freed->player_id)->toBe($players[0]->id);
    expect($freed->sequence_id)->toBeNull();
});

it('swaps a wild two for the matching real card, turning a dirty canastra clean', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', '5H', '6H', '7H', '8H', '2D'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '9H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/6/swap", [
        'playerId' => $players[0]->id,
        'code' => '9H',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('clean');
});

it('rejects swapping a position that already holds a face card', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', '5H'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '4H');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/1/swap", [
        'playerId' => $players[0]->id,
        'code' => '4H',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('nothing_to_swap');
});

it('rejects a swap code that does not match the expected rank and suit', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    swapMakeHandCard($game->id, $players[0]->id, '5D');

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5D',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('swap_card_mismatch');
});

it('rejects a swap when the code is not available in the teams hands', function () {
    [$game, $players, $sequence] = makeSequenceForSwap(['3H', '4H', 'W'], 'H', '3');
    // 5H is never given to any player's hand

    $response = $this->postJson("/api/sequences/{$sequence->id}/cards/2/swap", [
        'playerId' => $players[0]->id,
        'code' => '5H',
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('insufficient_cards_in_pool');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/SwapSequenceCardTest.php`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Create the swap exceptions**

`backend/app/Exceptions/Sequence/NothingToSwapException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class NothingToSwapException extends DomainException
{
    public function __construct(private readonly int $position)
    {
        parent::__construct("A posição {$position} já tem uma carta natural — não há curinga para trocar.");
    }

    public function context(): array
    {
        return ['position' => $this->position];
    }
}
```

`backend/app/Exceptions/Sequence/SwapCardMismatchException.php`:

```php
<?php

namespace App\Exceptions\Sequence;

use App\Exceptions\DomainException;

class SwapCardMismatchException extends DomainException
{
    public function __construct(
        private readonly string $code,
        private readonly string $expectedRank,
        private readonly string $suit,
    ) {
        parent::__construct("A carta \"{$code}\" não corresponde à posição esperada ({$expectedRank} de {$suit}).");
    }

    public function context(): array
    {
        return ['code' => $this->code, 'expectedRank' => $this->expectedRank, 'suit' => $this->suit];
    }
}
```

- [ ] **Step 4: Create `SwapSequenceCardData`**

```php
<?php

namespace App\Data\Sequence;

use Spatie\LaravelData\Data;

class SwapSequenceCardData extends Data
{
    public function __construct(
        public string $playerId,
        public string $code,
    ) {}

    public static function rules(): array
    {
        return [
            'playerId' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^(?:[2-9TJQKA][SHCD]|W)$/'],
        ];
    }
}
```

Save as `backend/app/Data/Sequence/SwapSequenceCardData.php`.

- [ ] **Step 5: Create the `SwapSequenceCard` action**

```php
<?php

namespace App\Actions\Sequence;

use App\Data\Sequence\SwapSequenceCardData;
use App\Exceptions\Sequence\NothingToSwapException;
use App\Exceptions\Sequence\SwapCardMismatchException;
use App\Models\Card;
use App\Models\Player;
use App\Models\Sequence;
use App\Support\Cards\CardCode;
use App\Support\Cards\CardPool;
use App\Support\Cards\RankOrder;
use App\Support\Sequence\SequenceLegality;
use App\Support\Sequence\Team;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SwapSequenceCard
{
    use AsAction;

    public Sequence $sequence;

    public Player $player;

    public function handle(Sequence $sequence, int $position, SwapSequenceCardData $data): Sequence
    {
        $this->sequence = $sequence;
        $this->player = Player::where('game_id', $sequence->game_id)->findOrFail($data->playerId);

        Team::ensure($this->player, $sequence->team);

        $oldCard = Card::where('sequence_id', $sequence->id)
            ->where('sequence_position', $position)
            ->firstOrFail();

        if ($oldCard->role !== 'wild') {
            throw new NothingToSwapException($position);
        }

        $startIndex = RankOrder::indexOf($sequence->start_rank);
        $expectedRank = SequenceLegality::expectedRankAt($startIndex, $position);

        if (CardCode::isJoker($data->code)
            || CardCode::rank($data->code) !== $expectedRank
            || CardCode::suit($data->code) !== $sequence->suit) {
            throw new SwapCardMismatchException($data->code, $expectedRank, $sequence->suit);
        }

        return DB::transaction(function () use ($data, $oldCard, $position) {
            $playerIds = Team::playerIds($this->sequence->game_id, $this->sequence->team);
            $claimed = CardPool::claimFromHands([$data->code], $playerIds)->first();

            $oldCard->update([
                'status' => 'hand',
                'player_id' => $this->player->id,
                'sequence_id' => null,
                'sequence_position' => null,
                'role' => null,
            ]);

            $claimed->update([
                'status' => 'table',
                'sequence_id' => $this->sequence->id,
                'sequence_position' => $position,
                'role' => 'face',
            ]);

            return $this->sequence->fresh('cards');
        });
    }
}
```

Save as `backend/app/Actions/Sequence/SwapSequenceCard.php`.

- [ ] **Step 6: Add `swap()` to `SequenceController`**

In `backend/app/Http/Controllers/Api/SequenceController.php`, add the imports:

```php
use App\Actions\Sequence\SwapSequenceCard;
use App\Data\Sequence\SwapSequenceCardData;
```

Add the method:

```php
    public function swap(Sequence $sequence, int $position, SwapSequenceCardData $data): JsonResponse
    {
        $sequence = SwapSequenceCard::run($sequence, $position, $data);

        return SequenceResource::make($sequence)->response();
    }
```

- [ ] **Step 7: Add the route**

In `backend/routes/api.php`, add:

```php
Route::post('/sequences/{sequence}/cards/{position}/swap', [SequenceController::class, 'swap']);
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Sequences/SwapSequenceCardTest.php`
Expected: all tests PASS.

- [ ] **Step 9: Run the full test suite**

Run: `cd backend && ./vendor/bin/pest`
Expected: all tests PASS, no regressions.

- [ ] **Step 10: Commit**

```bash
cd backend
git add tests/Feature/Sequences/SwapSequenceCardTest.php app/Exceptions/Sequence/NothingToSwapException.php app/Exceptions/Sequence/SwapCardMismatchException.php app/Data/Sequence/SwapSequenceCardData.php app/Actions/Sequence/SwapSequenceCard.php app/Http/Controllers/Api/SequenceController.php routes/api.php
git commit -m "feat: add POST /api/sequences/{sequence}/cards/{position}/swap"
```

---

## Self-Review Notes

- **Spec coverage:** `sequences`/`cards` schema (Task 1), domain exception base + global handler + `StorePlayerHand` retrofit (Task 2), legality engine + its exceptions (Task 3), `Team`/`CardPool` claiming (Task 4), create/extend/swap endpoints (Tasks 5–7) — every section of the spec maps to a task. The spec's full exception table (11 rows) is fully implemented: `InsufficientCardsInPoolException`, `PlayerNotInTeamException` (Tasks 2–4), `SequenceTooShortException`, `InvalidSequenceCardException`, `InvalidAceTrincaCardException`, `MaxWildJokerExceededException`, `MaxWildTwoExceededException`, `WildcardCoexistenceException`, `SequenceRankOutOfBoundsException` (Task 3), `NothingToSwapException`, `SwapCardMismatchException` (Task 7).
- **Type consistency:** `SequenceLegality`'s method names/signatures defined in Task 3 are used identically in Tasks 5–7 (`expectedRankAt`, `resolveRole`, `resolveAceTrincaRole`, `validateWildcardLimits`, `computeStatus`). `Team::of/ensure/playerIds` and `CardPool::claimFromHands` (Task 4) are consumed with matching signatures in Tasks 5–7. The `{code, role}` shape produced by every role-resolution path matches what `SequenceResource` and `SequenceLegality::computeStatus` expect.
- **Out of scope (carried over from the design spec):** `GET` endpoint for sequences, any frontend/display, the play-recording/turn screen, AI, history, end-of-round scoring, and duplicating this validation in the frontend (future work, once the game screen exists).

