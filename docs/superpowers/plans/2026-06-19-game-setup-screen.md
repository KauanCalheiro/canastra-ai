# Game Setup Screen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `/games/new` setup screen (decks, target score, players) that persists a new game to SQLite via a Nitro API route, with API tests (Vitest) and an end-to-end test (Playwright) written first.

**Architecture:** A Nitro server route (`POST /api/games`) validates the payload and writes to two SQLite tables (`games`, `players`) via a `better-sqlite3` singleton. The Nuxt page `/games/new` composes existing base components (`BaseSegmentedControl`, `BaseStepper`, `BaseInput`, `BaseBadge`, `BaseButton`) plus one new composite component (`GamePlayerRow`), and on submit calls the API and navigates to a placeholder `/games/[id]/initial-hand` route.

**Tech Stack:** Nuxt 4 / Vue 3 / Tailwind (existing). New: `better-sqlite3`, `uuid`, `vitest`, `@nuxt/test-utils`, `@playwright/test`.

## Global Constraints

- Decks ∈ `{1, 2, 3}`. Target score: integer, minimum `100`, stepper increment `100`, default `3000`. Players: array length `2` or `4`, names may be empty strings.
- Team is derived from seat index (`seatIndex % 2 === 0` → Dupla A, else Dupla B) — never stored directly.
- IDs are uuid v4 generated with the `uuid` npm package (not `crypto.randomUUID`).
- SQLite file path comes from `process.env.DB_PATH`, default `.data/canastra.sqlite`. No ORM, no migration framework — `CREATE TABLE IF NOT EXISTS` on first connection.
- No global store (Pinia) introduced in this iteration — page-local state only.
- Route for this screen: `/games/new`. On success, navigate to `/games/[id]/initial-hand` (placeholder page).
- All user-facing error messages in Portuguese.
- Spec: `docs/superpowers/specs/2026-06-19-game-setup-screen-design.md`.

---

## Task 1: Tooling — dependencies, Vitest, Playwright

**Files:**
- Modify: `package.json`
- Modify: `.gitignore`
- Create: `vitest.config.ts`
- Create: `playwright.config.ts`

**Interfaces:**
- Produces: `pnpm test:api` (runs Vitest against `tests/api/**/*.test.ts`), `pnpm test:e2e` (runs Playwright against `tests/e2e/`). Both used by Tasks 2 and 3.

- [ ] **Step 1: Install runtime and dev dependencies**

Run:
```bash
pnpm add better-sqlite3 uuid
pnpm add -D vitest @nuxt/test-utils @playwright/test @types/better-sqlite3
```

- [ ] **Step 2: Install the Playwright browser binary**

Run:
```bash
pnpm exec playwright install chromium
```
Expected: downloads the Chromium binary, exits 0.

- [ ] **Step 3: Create `vitest.config.ts`**

```typescript
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    include: ['tests/api/**/*.test.ts'],
    testTimeout: 30000,
    hookTimeout: 30000
  }
})
```

- [ ] **Step 4: Create `playwright.config.ts`**

```typescript
import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  use: {
    baseURL: 'http://localhost:3000'
  },
  webServer: {
    command: 'pnpm dev',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 60000,
    env: {
      DB_PATH: '.data/e2e-test.sqlite'
    }
  }
})
```

- [ ] **Step 5: Add test scripts to `package.json`**

In the `"scripts"` block, add:
```json
"test:api": "vitest run",
"test:e2e": "playwright test"
```

- [ ] **Step 6: Ignore the local SQLite data directory**

Append to `.gitignore`:
```
.data/
```

- [ ] **Step 7: Verify the tooling is wired up**

Run:
```bash
pnpm exec vitest --version
pnpm exec playwright --version
```
Expected: both print version numbers, exit 0. (No test files exist yet — `pnpm test:api` / `pnpm test:e2e` are exercised starting in Task 2.)

- [ ] **Step 8: Commit**

```bash
git add package.json pnpm-lock.yaml .gitignore vitest.config.ts playwright.config.ts
git commit -m "Add Vitest and Playwright tooling for API and E2E tests"
```

---

## Task 2: SQLite schema + `POST /api/games`

**Files:**
- Create: `server/utils/db.ts`
- Create: `server/api/games.post.ts`
- Test: `tests/api/games.post.test.ts`

**Interfaces:**
- Consumes: nothing from other tasks (uses `better-sqlite3`, `uuid` from Task 1).
- Produces: `getDb(): import('better-sqlite3').Database` from `server/utils/db.ts` (used only inside `server/api/games.post.ts` in this plan). HTTP contract: `POST /api/games` body `{ decks: number, targetScore: number, players: string[] }` → `200 { id: string }` on success, `400 { statusMessage: string }` on validation failure. Consumed by the frontend in Task 3.

- [ ] **Step 1: Write the failing API tests**

Create `tests/api/games.post.test.ts`:

```typescript
import { afterAll, describe, expect, it } from 'vitest'
import { $fetch, setup } from '@nuxt/test-utils/e2e'
import Database from 'better-sqlite3'
import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const testDbDir = mkdtempSync(join(tmpdir(), 'canastra-test-'))
const testDbPath = join(testDbDir, 'canastra.sqlite')

await setup({
  rootDir: process.cwd(),
  env: { DB_PATH: testDbPath }
})

afterAll(() => {
  rmSync(testDbDir, { recursive: true, force: true })
})

describe('POST /api/games', () => {
  it('creates a game with 2 players and persists it', async () => {
    const result = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: { decks: 2, targetScore: 3000, players: ['Ana', 'Bruno'] }
    })

    expect(result.id).toBeTypeOf('string')

    const db = new Database(testDbPath, { readonly: true })
    const game = db.prepare('SELECT * FROM games WHERE id = ?').get(result.id) as Record<string, unknown>
    const players = db.prepare('SELECT * FROM players WHERE game_id = ? ORDER BY seat_index').all(result.id) as Record<string, unknown>[]
    db.close()

    expect(game).toMatchObject({ decks: 2, target_score: 3000 })
    expect(players).toHaveLength(2)
    expect(players[0]).toMatchObject({ seat_index: 0, name: 'Ana' })
    expect(players[1]).toMatchObject({ seat_index: 1, name: 'Bruno' })
  })

  it('creates a game with 4 players', async () => {
    const result = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: { decks: 1, targetScore: 1500, players: ['Ana', 'Bruno', 'Carla', 'Diego'] }
    })

    const db = new Database(testDbPath, { readonly: true })
    const players = db.prepare('SELECT * FROM players WHERE game_id = ? ORDER BY seat_index').all(result.id) as Record<string, unknown>[]
    db.close()

    expect(players).toHaveLength(4)
  })

  it('rejects an invalid number of decks', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 5, targetScore: 3000, players: ['Ana', 'Bruno'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })

  it('rejects a target score below 100', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 2, targetScore: 50, players: ['Ana', 'Bruno'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })

  it('rejects a player count outside 2 or 4', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 2, targetScore: 3000, players: ['Ana', 'Bruno', 'Carla'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })
})
```

- [ ] **Step 2: Run the tests and verify they fail**

Run: `pnpm test:api`
Expected: FAIL — `server/api/games.post.ts` does not exist yet, requests return 404.

- [ ] **Step 3: Implement `server/utils/db.ts`**

```typescript
import Database from 'better-sqlite3'
import { mkdirSync } from 'node:fs'
import { dirname } from 'node:path'

let db: Database.Database | null = null

export function getDb(): Database.Database {
  if (db) return db

  const path = process.env.DB_PATH ?? '.data/canastra.sqlite'
  mkdirSync(dirname(path), { recursive: true })

  db = new Database(path)
  db.exec(`
    CREATE TABLE IF NOT EXISTS games (
      id TEXT PRIMARY KEY,
      decks INTEGER NOT NULL,
      target_score INTEGER NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS players (
      id TEXT PRIMARY KEY,
      game_id TEXT NOT NULL REFERENCES games(id),
      seat_index INTEGER NOT NULL,
      name TEXT NOT NULL DEFAULT ''
    );
  `)

  return db
}
```

- [ ] **Step 4: Implement `server/api/games.post.ts`**

```typescript
import { v4 as uuidv4 } from 'uuid'
import { getDb } from '../utils/db'

interface CreateGameBody {
  decks: number
  targetScore: number
  players: string[]
}

export default defineEventHandler(async (event) => {
  const body = await readBody<CreateGameBody>(event)

  if (![1, 2, 3].includes(body.decks)) {
    throw createError({ statusCode: 400, statusMessage: 'Número de baralhos deve ser 1, 2 ou 3.' })
  }
  if (!Number.isInteger(body.targetScore) || body.targetScore < 100) {
    throw createError({ statusCode: 400, statusMessage: 'Pontuação para vencer deve ser um inteiro maior ou igual a 100.' })
  }
  if (!Array.isArray(body.players) || (body.players.length !== 2 && body.players.length !== 4)) {
    throw createError({ statusCode: 400, statusMessage: 'É necessário informar 2 ou 4 jogadores.' })
  }

  const db = getDb()
  const gameId = uuidv4()

  const insert = db.transaction(() => {
    db.prepare(
      'INSERT INTO games (id, decks, target_score) VALUES (?, ?, ?)'
    ).run(gameId, body.decks, body.targetScore)

    const insertPlayer = db.prepare(
      'INSERT INTO players (id, game_id, seat_index, name) VALUES (?, ?, ?, ?)'
    )
    body.players.forEach((name, seatIndex) => {
      insertPlayer.run(uuidv4(), gameId, seatIndex, name)
    })
  })

  insert()

  return { id: gameId }
})
```

- [ ] **Step 5: Run the tests and verify they pass**

Run: `pnpm test:api`
Expected: PASS — all 5 tests green.

- [ ] **Step 6: Commit**

```bash
git add server/utils/db.ts server/api/games.post.ts tests/api/games.post.test.ts
git commit -m "Add POST /api/games with SQLite persistence and API tests"
```

---

## Task 3: Setup screen UI + E2E test

**Files:**
- Modify: `app/components/base/stepper.vue`
- Modify: `app/components/base/badge.vue`
- Create: `app/components/game/player-row.vue` (auto-imported as `<GamePlayerRow>`)
- Create: `app/pages/games/new.vue`
- Create: `app/pages/games/[id]/initial-hand.vue`
- Test: `tests/e2e/games-new.spec.ts`

**Interfaces:**
- Consumes: `POST /api/games` contract from Task 2 (`{ decks, targetScore, players }` → `{ id }` or 400 error with `statusMessage`).
- Produces: nothing consumed by later tasks (this is the last task in the plan).

- [ ] **Step 1: Write the failing E2E test**

Create `tests/e2e/games-new.spec.ts`:

```typescript
import { expect, test } from '@playwright/test'

test('cria uma partida e navega para a tela de mão inicial', async ({ page }) => {
  await page.goto('/games/new')

  const nameInputs = page.getByPlaceholder('Nome do jogador')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')

  await page.getByRole('button', { name: '3', exact: true }).click()
  await page.getByRole('button', { name: 'Registrar minha mão →' }).click()

  await page.waitForURL(/\/games\/[\w-]+\/initial-hand$/)
  await expect(page.getByText('Partida')).toBeVisible()
})
```

- [ ] **Step 2: Run the test and verify it fails**

Run: `pnpm test:e2e`
Expected: FAIL — `/games/new` does not exist (404), or `Nome do jogador` placeholder not found.

- [ ] **Step 3: Add a `min` prop to `BaseStepper`**

Replace the contents of `app/components/base/stepper.vue`:

```vue
<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: number
  step?: number
  min?: number
}>(), {
  step: 1,
  min: -Infinity
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

function decrement() {
  const next = props.modelValue - props.step
  if (next < props.min) return
  emit('update:modelValue', next)
}

function increment() {
  emit('update:modelValue', props.modelValue + props.step)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
      @click="decrement"
    >
      −
    </button>
    <span class="rounded-md border border-ink/15 px-4 py-3 text-center font-body font-bold text-[18px] text-ink">
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
      @click="increment"
    >
      +
    </button>
  </div>
</template>
```

- [ ] **Step 4: Add `team-a` / `team-b` variants to `BaseBadge`**

Replace the contents of `app/components/base/badge.vue`:

```vue
<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'positive' | 'negative' | 'livre' | 'obriga' | 'team-a' | 'team-b'
}>(), {
  variant: 'positive'
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-pill px-3 py-1 font-body font-bold text-[10px] uppercase tracking-wide"
    :class="{
      'bg-primary-pale text-ink-deep': variant === 'positive' || variant === 'livre' || variant === 'team-a',
      'bg-negative/10 text-negative': variant === 'negative' || variant === 'obriga',
      'bg-canvas-soft text-body': variant === 'team-b'
    }"
  >
    <slot />
  </span>
</template>
```

- [ ] **Step 5: Create `app/components/game/player-row.vue`**

```vue
<script setup lang="ts">
defineProps<{
  name: string
  team: 'A' | 'B'
  canMoveUp: boolean
  canMoveDown: boolean
}>()

defineEmits<{
  'update:name': [value: string]
  moveUp: []
  moveDown: []
}>()
</script>

<template>
  <div class="flex items-center gap-3">
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-pill font-body font-bold text-[16px]"
      :class="team === 'A' ? 'bg-primary text-ink-deep' : 'bg-ink text-white'"
    >
      {{ name.trim().charAt(0).toUpperCase() }}
    </div>
    <BaseInput
      :model-value="name"
      placeholder="Nome do jogador"
      class="flex-1"
      @update:model-value="$emit('update:name', $event)"
    />
    <BaseBadge :variant="team === 'A' ? 'team-a' : 'team-b'">
      Dupla {{ team }}
    </BaseBadge>
    <div class="flex flex-col gap-1">
      <button
        type="button"
        class="text-[12px] text-ink disabled:opacity-20"
        :disabled="!canMoveUp"
        @click="$emit('moveUp')"
      >
        ▲
      </button>
      <button
        type="button"
        class="text-[12px] text-ink disabled:opacity-20"
        :disabled="!canMoveDown"
        @click="$emit('moveDown')"
      >
        ▼
      </button>
    </div>
  </div>
</template>
```

- [ ] **Step 6: Create `app/pages/games/new.vue`**

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const deckOptions = [
  { label: '1', value: '1' },
  { label: '2', value: '2' },
  { label: '3', value: '3' }
]
const decks = ref('2')

const targetScore = ref(3000)
const obrigaScore = computed(() => Math.ceil(targetScore.value / 2))

const playerCountOptions = [
  { label: '2 jogadores', value: '2' },
  { label: '4 jogadores', value: '4' }
]
const playerCount = ref('2')
const players = ref<string[]>(['', ''])

watch(playerCount, (count) => {
  const size = Number(count)
  if (size > players.value.length) {
    players.value = [...players.value, ...Array(size - players.value.length).fill('')]
  } else {
    players.value = players.value.slice(0, size)
  }
})

function teamFor(index: number): 'A' | 'B' {
  return index % 2 === 0 ? 'A' : 'B'
}

function moveUp(index: number) {
  if (index === 0) return
  const next = [...players.value]
  ;[next[index - 1], next[index]] = [next[index], next[index - 1]]
  players.value = next
}

function moveDown(index: number) {
  if (index === players.value.length - 1) return
  const next = [...players.value]
  ;[next[index], next[index + 1]] = [next[index + 1], next[index]]
  players.value = next
}

const error = ref<string | null>(null)
const submitting = ref(false)

async function handleSubmit() {
  error.value = null
  submitting.value = true
  try {
    const { id } = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: {
        decks: Number(decks.value),
        targetScore: targetScore.value,
        players: players.value
      }
    })
    await navigateTo(`/games/${id}/initial-hand`)
  } catch (err) {
    error.value = (err as { data?: { statusMessage?: string } })?.data?.statusMessage
      ?? 'Não foi possível registrar a partida.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-canvas-soft">
    <header class="px-6 py-5">
      <h1 class="font-display font-black text-[22px] text-ink">canastra ai</h1>
    </header>

    <div class="flex-1 space-y-8 overflow-y-auto px-6 pb-32">
      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Número de baralhos</h2>
        <BaseSegmentedControl v-model="decks" :options="deckOptions" />
      </section>

      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Pontuação para vencer</h2>
        <BaseStepper v-model="targetScore" :step="100" :min="100" />
        <p class="mt-2 font-body text-[13px] text-body">
          Obriga em {{ obrigaScore }} pontos (metade da meta)
        </p>
      </section>

      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Jogadores e ordem da mesa</h2>
        <BaseSegmentedControl v-model="playerCount" :options="playerCountOptions" class="mb-4" />
        <div class="space-y-3">
          <GamePlayerRow
            v-for="(name, index) in players"
            :key="index"
            :name="name"
            :team="teamFor(index)"
            :can-move-up="index > 0"
            :can-move-down="index < players.length - 1"
            @update:name="players[index] = $event"
            @move-up="moveUp(index)"
            @move-down="moveDown(index)"
          />
        </div>
      </section>

      <p v-if="error" class="font-body text-[14px] text-negative">{{ error }}</p>
    </div>

    <footer class="fixed inset-x-0 bottom-0 bg-canvas-soft px-6 py-4">
      <BaseButton
        variant="primary"
        class="w-full justify-center"
        :disabled="submitting"
        @click="handleSubmit"
      >
        Registrar minha mão →
      </BaseButton>
    </footer>
  </div>
</template>
```

- [ ] **Step 7: Create the placeholder `app/pages/games/[id]/initial-hand.vue`**

```vue
<script setup lang="ts">
const route = useRoute()
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas-soft px-6 text-center">
    <p class="font-body text-[16px] text-ink">
      Partida {{ route.params.id }} criada. Registrar mão em breve.
    </p>
  </div>
</template>
```

- [ ] **Step 8: Run the E2E test and verify it passes**

Run: `pnpm test:e2e`
Expected: PASS — 1 test green.

- [ ] **Step 9: Commit**

```bash
git add app/components/base/stepper.vue app/components/base/badge.vue app/components/game/player-row.vue app/pages/games/new.vue "app/pages/games/[id]/initial-hand.vue" tests/e2e/games-new.spec.ts
git commit -m "Add /games/new setup screen wired to the games API"
```
