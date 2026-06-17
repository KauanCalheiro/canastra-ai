# Styleguide Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `/styleguide` page that showcases a first set of base presentational components, styled with Tailwind CSS against the Canastra AI design tokens.

**Architecture:** Add Tailwind CSS (via `@nuxtjs/tailwindcss`) and Google Fonts (via `@nuxtjs/google-fonts`) to the Nuxt app, configure the Tailwind theme with the Canastra AI design tokens, then build eight presentational components under `app/components/base/` and a single `app/pages/styleguide.vue` page that demonstrates each one. Each task adds one component plus its corresponding section on the page, so the page grows incrementally and stays demonstrable after every task.

**Tech Stack:** Nuxt 4, Vue 3 `<script setup>`, TypeScript, Tailwind CSS (`@nuxtjs/tailwindcss`), `@nuxtjs/google-fonts`, pnpm.

## Global Constraints

- Design tokens come from `design_handoff_canastra_ai/README.md`, not the generic `DESIGN.md` Wise brand doc.
- Colors: `ink #0e0f0c`, `primary #9fe870`, `primary-active #cdffad`, `primary-neutral #7ac95a`, `primary-pale #e9f9dd`, `canvas-soft #e8ebe6`, `body #454745`, `mute #717570`, `positive #2ead4b`, `negative #d03238`, `ink-deep #163300`, `card-red #d03238`, `card-black #0e0f0c`.
- Border radii: `pill 9999px`, `xl 24px`, `card 16px`, `md 12px`, `sm 8px`, `card-face 6px`.
- Fonts: `display` = Manrope (weight 900 only), `body` = Inter (400/600/700).
- All components live under `app/components/base/`, filenames lowercase/kebab-case (Nuxt auto-import gives `Base`-prefixed PascalCase tags, e.g. `base/segmented-control.vue` → `<BaseSegmentedControl>`).
- Components are presentational only — no game logic, no global state. Interactive ones (`stepper`, `input`, `segmented-control`) take `v-model`.
- Route is `/styleguide`, not linked from any app navigation (dev-only, direct URL access).
- No automated tests — project has no test infrastructure yet (tracked separately). Verification is via running the dev server and checking rendered HTML with `curl`/`grep`.
- Package manager is pnpm (already configured in this project — do not introduce npm/yarn lockfiles).

---

### Task 1: Install and configure Tailwind CSS + Google Fonts

**Files:**
- Modify: `package.json` (via `pnpm add`)
- Modify: `nuxt.config.ts`
- Create: `tailwind.config.ts`

**Interfaces:**
- Produces: Tailwind utility classes available in all `.vue` files under `app/`, using the theme keys `primary`, `primary-active`, `primary-neutral`, `primary-pale`, `canvas-soft`, `ink`, `ink-deep`, `body`, `mute`, `positive`, `negative`, `card-red`, `card-black` (colors) and `pill`, `xl`, `card`, `md`, `sm`, `card-face` (border radii), and `font-display` / `font-body` (font families).

- [ ] **Step 1: Install the Tailwind and Google Fonts Nuxt modules**

```bash
pnpm add -D @nuxtjs/tailwindcss @nuxtjs/google-fonts
```

- [ ] **Step 2: Register the modules and Google Fonts config in `nuxt.config.ts`**

Replace the full file contents of `nuxt.config.ts` with:

```ts
// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/google-fonts'],
  googleFonts: {
    families: {
      Manrope: [900],
      Inter: [400, 600, 700]
    },
    display: 'swap'
  }
})
```

- [ ] **Step 3: Create `tailwind.config.ts` with the Canastra AI design tokens**

```ts
import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  content: [
    './app/components/**/*.vue',
    './app/pages/**/*.vue',
    './app/app.vue'
  ],
  theme: {
    extend: {
      colors: {
        ink: '#0e0f0c',
        primary: '#9fe870',
        'primary-active': '#cdffad',
        'primary-neutral': '#7ac95a',
        'primary-pale': '#e9f9dd',
        'canvas-soft': '#e8ebe6',
        body: '#454745',
        mute: '#717570',
        positive: '#2ead4b',
        negative: '#d03238',
        'ink-deep': '#163300',
        'card-red': '#d03238',
        'card-black': '#0e0f0c'
      },
      borderRadius: {
        pill: '9999px',
        xl: '24px',
        card: '16px',
        md: '12px',
        sm: '8px',
        'card-face': '6px'
      },
      fontFamily: {
        display: ['Manrope', 'sans-serif'],
        body: ['Inter', 'sans-serif']
      }
    }
  }
}
```

- [ ] **Step 4: Verify the app still boots with Tailwind wired in**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3099
grep -i "error" /tmp/styleguide_task.log || echo "no errors"
pkill -f "nuxt dev"
```

Expected: HTTP code `200`, and `no errors` printed (no `ERROR` lines in the log).

- [ ] **Step 5: Commit**

```bash
git add package.json pnpm-lock.yaml nuxt.config.ts tailwind.config.ts
git commit -m "Add Tailwind CSS and Google Fonts with Canastra AI design tokens"
```

---

### Task 2: Wire up file-based routing and create the `/styleguide` page shell

**Files:**
- Modify: `app/app.vue`
- Create: `app/pages/styleguide.vue`

**Interfaces:**
- Consumes: Tailwind theme from Task 1 (`bg-canvas-soft`, `font-display`, `text-ink`).
- Produces: route `/styleguide` rendering a page shell that later tasks append sections to.

- [ ] **Step 1: Replace `app/app.vue` to render `<NuxtPage />` instead of the welcome screen**

```vue
<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtPage />
  </div>
</template>
```

- [ ] **Step 2: Create `app/pages/styleguide.vue` with the page shell**

```vue
<template>
  <div class="min-h-screen bg-canvas-soft px-6 py-10">
    <h1 class="mb-8 font-display font-black text-[32px] text-ink">
      Styleguide
    </h1>
  </div>
</template>
```

- [ ] **Step 3: Verify the route renders**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -o "Styleguide" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `Styleguide` printed (found in the rendered HTML).

- [ ] **Step 4: Commit**

```bash
git add app/app.vue app/pages/styleguide.vue
git commit -m "Add /styleguide page shell with NuxtPage routing"
```

---

### Task 3: BaseButton + Buttons section

**Files:**
- Create: `app/components/base/button.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BaseButton variant="primary" | "secondary" | "outline">` (default `"primary"`), default slot renders the label.

- [ ] **Step 1: Create `app/components/base/button.vue`**

```vue
<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'outline'
}>(), {
  variant: 'primary'
})
</script>

<template>
  <button
    class="inline-flex items-center justify-center rounded-pill px-6 py-3 font-body font-bold text-[16px] transition-colors"
    :class="{
      'bg-primary text-ink hover:bg-primary-active': variant === 'primary',
      'bg-white text-ink border border-ink/15': variant === 'secondary',
      'bg-transparent text-ink border border-ink': variant === 'outline'
    }"
  >
    <slot />
  </button>
</template>
```

- [ ] **Step 2: Add the Buttons section to `app/pages/styleguide.vue`**

Full file contents:

```vue
<template>
  <div class="min-h-screen bg-canvas-soft px-6 py-10">
    <h1 class="mb-8 font-display font-black text-[32px] text-ink">
      Styleguide
    </h1>

    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Buttons</h2>
      <p class="mb-4 font-body text-[14px] text-body">Primary, secondary and outline variants.</p>
      <BaseCard class="flex gap-4">
        <BaseButton variant="primary">Primary</BaseButton>
        <BaseButton variant="secondary">Secondary</BaseButton>
        <BaseButton variant="outline">Outline</BaseButton>
      </BaseCard>
    </section>
  </div>
</template>
```

Note: this references `<BaseCard>`, built in Task 4. The page won't render correctly until Task 4 is done — that's expected; verify this task by checking the button markup directly instead of the full render (see Step 3).

- [ ] **Step 3: Verify the button markup is present**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log || true
grep -o "Primary" /tmp/styleguide.html
grep -o "rounded-pill" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `Primary` and `rounded-pill` both found. If the log shows `[Vue warn]: Failed to resolve component: BaseCard`, that's expected at this point and not a failure — `BaseCard` doesn't exist until Task 4.

- [ ] **Step 4: Commit**

```bash
git add app/components/base/button.vue app/pages/styleguide.vue
git commit -m "Add BaseButton component and Buttons styleguide section"
```

---

### Task 4: BaseCard + Card section

**Files:**
- Create: `app/components/base/card.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BaseCard surface="white" | "sage">` (default `"white"`), default slot renders the card body.

- [ ] **Step 1: Create `app/components/base/card.vue`**

```vue
<script setup lang="ts">
withDefaults(defineProps<{
  surface?: 'white' | 'sage'
}>(), {
  surface: 'white'
})
</script>

<template>
  <div
    class="rounded-card p-6"
    :class="surface === 'sage' ? 'bg-canvas-soft' : 'bg-white'"
  >
    <slot />
  </div>
</template>
```

- [ ] **Step 2: Add the Card section to `app/pages/styleguide.vue`**

Insert this new `<section>` right after the Buttons section (before the closing `</div>`):

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Card</h2>
      <p class="mb-4 font-body text-[14px] text-body">White and sage surfaces.</p>
      <div class="flex gap-4">
        <BaseCard surface="white">
          <p class="font-body text-[14px] text-ink">White card</p>
        </BaseCard>
        <BaseCard surface="sage">
          <p class="font-body text-[14px] text-ink">Sage card</p>
        </BaseCard>
      </div>
    </section>
```

- [ ] **Step 3: Verify both the Buttons and Card sections render (Buttons' `BaseCard` dependency is now satisfied)**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
grep -o "White card" /tmp/styleguide.html
grep -o "Sage card" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `no errors`, plus both `White card` and `Sage card` found.

- [ ] **Step 4: Commit**

```bash
git add app/components/base/card.vue app/pages/styleguide.vue
git commit -m "Add BaseCard component and Card styleguide section"
```

---

### Task 5: BaseBadge + Badge section

**Files:**
- Create: `app/components/base/badge.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BaseBadge variant="positive" | "negative" | "livre" | "obriga">` (default `"positive"`), default slot renders the label.

- [ ] **Step 1: Create `app/components/base/badge.vue`**

```vue
<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'positive' | 'negative' | 'livre' | 'obriga'
}>(), {
  variant: 'positive'
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-pill px-3 py-1 font-body font-bold text-[10px] uppercase tracking-wide"
    :class="{
      'bg-primary-pale text-ink-deep': variant === 'positive' || variant === 'livre',
      'bg-negative/10 text-negative': variant === 'negative' || variant === 'obriga'
    }"
  >
    <slot />
  </span>
</template>
```

- [ ] **Step 2: Add the Badge section to `app/pages/styleguide.vue`**

Insert this `<section>` right after the Card section:

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Badge</h2>
      <p class="mb-4 font-body text-[14px] text-body">Positive, negative, livre and obriga states.</p>
      <BaseCard class="flex gap-3">
        <BaseBadge variant="positive">Positive</BaseBadge>
        <BaseBadge variant="negative">Negative</BaseBadge>
        <BaseBadge variant="livre">Livre</BaseBadge>
        <BaseBadge variant="obriga">Obriga</BaseBadge>
      </BaseCard>
    </section>
```

- [ ] **Step 3: Verify**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
grep -o "Obriga" /tmp/styleguide.html
grep -o "bg-negative/10" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `no errors`, `Obriga` found, `bg-negative/10` found.

- [ ] **Step 4: Commit**

```bash
git add app/components/base/badge.vue app/pages/styleguide.vue
git commit -m "Add BaseBadge component and Badge styleguide section"
```

---

### Task 6: BaseInput + BaseStepper + Input/Stepper section

**Files:**
- Create: `app/components/base/input.vue`
- Create: `app/components/base/stepper.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BaseInput v-model="string">`, `<BaseStepper v-model="number" :step="number">` (default `step` is `1`).

- [ ] **Step 1: Create `app/components/base/input.vue`**

```vue
<script setup lang="ts">
defineProps<{
  modelValue: string
  placeholder?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <input
    :value="modelValue"
    :placeholder="placeholder"
    class="rounded-md border border-ink bg-white px-4 py-3 font-body text-[16px] text-ink"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  >
</template>
```

- [ ] **Step 2: Create `app/components/base/stepper.vue`**

```vue
<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: number
  step?: number
}>(), {
  step: 1
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

function decrement() {
  emit('update:modelValue', props.modelValue - props.step)
}

function increment() {
  emit('update:modelValue', props.modelValue + props.step)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink bg-white font-body font-bold text-[18px] text-ink"
      @click="decrement"
    >
      −
    </button>
    <span class="rounded-md border border-ink px-4 py-3 text-center font-body font-bold text-[18px] text-ink">
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink bg-white font-body font-bold text-[18px] text-ink"
      @click="increment"
    >
      +
    </button>
  </div>
</template>
```

- [ ] **Step 3: Add the Input/Stepper section to `app/pages/styleguide.vue`**

This section needs local reactive state, so the page now needs a `<script setup>` block. Insert the script block right before the `<template>` tag (at the very top of the file), and insert the section after the Badge section:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const inputValue = ref('')
const stepperValue = ref(2)
</script>
```

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Input &amp; Stepper</h2>
      <p class="mb-4 font-body text-[14px] text-body">Text input and numeric stepper, both interactive.</p>
      <BaseCard class="flex items-center gap-6">
        <BaseInput v-model="inputValue" placeholder="Nome do jogador" />
        <BaseStepper v-model="stepperValue" :step="100" />
      </BaseCard>
    </section>
```

- [ ] **Step 4: Verify**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
grep -o "Nome do jogador" /tmp/styleguide.html
grep -o ">2<" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `no errors`, placeholder text found, stepper's initial value `2` found in the rendered output.

- [ ] **Step 5: Commit**

```bash
git add app/components/base/input.vue app/components/base/stepper.vue app/pages/styleguide.vue
git commit -m "Add BaseInput and BaseStepper components and their styleguide section"
```

---

### Task 7: BaseSegmentedControl + Segmented Control section

**Files:**
- Create: `app/components/base/segmented-control.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BaseSegmentedControl :options="{label, value}[]" v-model="string">`.

- [ ] **Step 1: Create `app/components/base/segmented-control.vue`**

```vue
<script setup lang="ts">
defineProps<{
  options: { label: string, value: string }[]
  modelValue: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="flex gap-2">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      class="h-[46px] flex-1 rounded-md border font-body font-bold text-[16px] text-ink"
      :class="option.value === modelValue ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
      @click="$emit('update:modelValue', option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
```

- [ ] **Step 2: Add the Segmented Control section to `app/pages/styleguide.vue`**

Add `deckCount` state to the existing `<script setup>` block (alongside `inputValue` and `stepperValue`):

```ts
const deckCount = ref('2')
const deckOptions = [
  { label: '1', value: '1' },
  { label: '2', value: '2' },
  { label: '3', value: '3' }
]
```

Insert this section after the Input/Stepper section:

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Segmented Control</h2>
      <p class="mb-4 font-body text-[14px] text-body">Number-of-decks selector, interactive.</p>
      <BaseCard>
        <BaseSegmentedControl v-model="deckCount" :options="deckOptions" />
      </BaseCard>
    </section>
```

- [ ] **Step 3: Verify**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
grep -o "Segmented Control" /tmp/styleguide.html
grep -o "bg-primary" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `no errors`, `Segmented Control` found, at least one `bg-primary` occurrence (the active "2" option).

- [ ] **Step 4: Commit**

```bash
git add app/components/base/segmented-control.vue app/pages/styleguide.vue
git commit -m "Add BaseSegmentedControl component and its styleguide section"
```

---

### Task 8: BasePlayingCard + Playing Card section

**Files:**
- Create: `app/components/base/playing-card.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BasePlayingCard rank="string" suit="hearts" | "diamonds" | "clubs" | "spades" :faceDown="boolean" :isJoker="boolean">` (defaults: `rank="A"`, `suit="spades"`, `faceDown=false`, `isJoker=false`).

- [ ] **Step 1: Create `app/components/base/playing-card.vue`**

```vue
<script setup lang="ts">
const props = withDefaults(defineProps<{
  rank?: string
  suit?: 'hearts' | 'diamonds' | 'clubs' | 'spades'
  faceDown?: boolean
  isJoker?: boolean
}>(), {
  rank: 'A',
  suit: 'spades',
  faceDown: false,
  isJoker: false
})

const suitSymbol = {
  hearts: '♥',
  diamonds: '♦',
  clubs: '♣',
  spades: '♠'
}

const isRedSuit = props.suit === 'hearts' || props.suit === 'diamonds'
</script>

<template>
  <div
    class="flex h-[60px] w-[42px] flex-col items-center justify-center rounded-card-face border border-ink font-body font-bold"
    :class="faceDown ? 'bg-ink' : 'bg-white'"
  >
    <template v-if="!faceDown">
      <span v-if="isJoker" class="text-[18px] text-primary">W</span>
      <template v-else>
        <span class="text-[14px]" :class="isRedSuit ? 'text-card-red' : 'text-card-black'">{{ rank }}</span>
        <span class="text-[16px]" :class="isRedSuit ? 'text-card-red' : 'text-card-black'">{{ suitSymbol[suit] }}</span>
      </template>
    </template>
  </div>
</template>
```

- [ ] **Step 2: Add the Playing Card section to `app/pages/styleguide.vue`**

Insert this section after the Segmented Control section:

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Playing Card</h2>
      <p class="mb-4 font-body text-[14px] text-body">Black suit, red suit, joker and face-down.</p>
      <BaseCard class="flex gap-3">
        <BasePlayingCard rank="K" suit="spades" />
        <BasePlayingCard rank="7" suit="hearts" />
        <BasePlayingCard is-joker />
        <BasePlayingCard face-down />
      </BaseCard>
    </section>
```

- [ ] **Step 3: Verify**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
grep -o "Playing Card" /tmp/styleguide.html
grep -o "text-card-red" /tmp/styleguide.html
grep -o "bg-ink" /tmp/styleguide.html
pkill -f "nuxt dev"
```

Expected: `no errors`, `Playing Card` found, `text-card-red` found (the hearts card), `bg-ink` found (the face-down card).

- [ ] **Step 4: Commit**

```bash
git add app/components/base/playing-card.vue app/pages/styleguide.vue
git commit -m "Add BasePlayingCard component and its styleguide section"
```

---

### Task 9: BasePlayerChip + Player Chip section

**Files:**
- Create: `app/components/base/player-chip.vue`
- Modify: `app/pages/styleguide.vue`

**Interfaces:**
- Produces: `<BasePlayerChip initial="string" name="string" team="A" | "B" :active="boolean">` (defaults: `team="A"`, `active=true`).

- [ ] **Step 1: Create `app/components/base/player-chip.vue`**

```vue
<script setup lang="ts">
withDefaults(defineProps<{
  initial: string
  name: string
  team?: 'A' | 'B'
  active?: boolean
}>(), {
  team: 'A',
  active: true
})
</script>

<template>
  <div class="flex items-center gap-2" :class="active ? 'opacity-100' : 'opacity-50'">
    <span
      class="flex h-8 w-8 items-center justify-center rounded-full font-body font-bold text-[12px]"
      :class="team === 'A' ? 'bg-primary text-ink-deep' : 'bg-canvas-soft text-ink'"
    >
      {{ initial }}
    </span>
    <span class="font-body font-semibold text-[14px] text-ink">{{ name }}</span>
  </div>
</template>
```

- [ ] **Step 2: Add the Player Chip section to `app/pages/styleguide.vue`**

Insert this section after the Playing Card section (this is the last section):

```vue
    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Player Chip</h2>
      <p class="mb-4 font-body text-[14px] text-body">Team A / Team B, active and inactive.</p>
      <BaseCard class="flex gap-6">
        <BasePlayerChip initial="A" name="Ana" team="A" :active="true" />
        <BasePlayerChip initial="B" name="Bruno" team="A" :active="false" />
        <BasePlayerChip initial="C" name="Carla" team="B" :active="true" />
        <BasePlayerChip initial="D" name="Diego" team="B" :active="false" />
      </BaseCard>
    </section>
```

- [ ] **Step 3: Verify the full page renders end-to-end**

```bash
pnpm exec nuxi dev --port 3099 > /tmp/styleguide_task.log 2>&1 &
sleep 10
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3099/styleguide
curl -s http://localhost:3099/styleguide -o /tmp/styleguide.html
grep -i "error" /tmp/styleguide_task.log && echo "FOUND ERRORS" || echo "no errors"
for marker in "Buttons" "Card" "Badge" "Input &amp; Stepper" "Segmented Control" "Playing Card" "Player Chip" "Ana" "Diego"; do
  grep -q "$marker" /tmp/styleguide.html && echo "OK: $marker" || echo "MISSING: $marker"
done
pkill -f "nuxt dev"
```

Expected: HTTP code `200`, `no errors`, and `OK:` printed for every marker (no `MISSING:` lines).

- [ ] **Step 4: Commit**

```bash
git add app/components/base/player-chip.vue app/pages/styleguide.vue
git commit -m "Add BasePlayerChip component and its styleguide section"
```
