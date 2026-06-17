# Styleguide page — design

## Context

The project has no UI components yet — only the fresh Nuxt minimal scaffold. We need a `/styleguide` page that showcases a first set of base components, built against the Canastra AI design tokens (not the generic Wise marketing doc), so future screens can reuse a consistent component library.

Source of truth for tokens: `design_handoff_canastra_ai/README.md` (colors, typography, radii) — this is the project-specific adaptation (Manrope + Inter, card radius 14–16px, pill radius 24px only on CTA buttons), not the generic `DESIGN.md` Wise brand doc.

## Tooling

- Add `@nuxtjs/tailwindcss` module to the Nuxt app.
- `tailwind.config.ts` extends the theme with:
  - **colors**: `ink (#0e0f0c)`, `primary (#9fe870)`, `primary-active (#cdffad)`, `primary-neutral (#7ac95a)`, `primary-pale (#e9f9dd)`, `canvas-soft (#e8ebe6)`, `body (#454745)`, `mute (#717570)`, `positive (#2ead4b)`, `negative (#d03238)`, `ink-deep (#163300)`, `card-red (#d03238)`, `card-black (#0e0f0c)`.
  - **borderRadius**: `pill (9999px)`, `xl (24px)`, `card (16px)`, `md (12px)`, `sm (8px)`, `card-face (6px)`.
  - **fontFamily**: `display: ['Manrope']`, `body: ['Inter']`.
- Load Manrope (weight 900) and Inter (400/600/700) via Google Fonts, either through `@nuxtjs/google-fonts` or a `<link>` tag in `nuxt.config.ts` `app.head`.

## Components

All under `app/components/base/`, filenames lowercase/kebab-case. Nuxt's folder-based auto-import gives each a `Base`-prefixed PascalCase tag.

| File | Tag | Purpose |
|---|---|---|
| `base/button.vue` | `<BaseButton>` | `variant: 'primary' \| 'secondary' \| 'outline'`. Pill 24px, `Inter 700`. Primary = `#9fe870` bg / ink text. Secondary = white bg / `rgba(14,15,12,.15)` border. Outline = transparent bg / ink border. |
| `base/card.vue` | `<BaseCard>` | Slot-based container. `surface: 'white' \| 'sage'` prop. `border-radius: 16px`. |
| `base/badge.vue` | `<BaseBadge>` | `variant: 'positive' \| 'negative' \| 'livre' \| 'obriga'`. Pill, uppercase, `Inter 700` 9–10px, using the handoff's semantic color pairs. |
| `base/input.vue` | `<BaseInput>` | Text input, 1px ink border, `border-radius: 12px`, `v-model` support. |
| `base/stepper.vue` | `<BaseStepper>` | `−`/`+` buttons (48px, ink border, `border-radius: 12px`) + centered numeric value, `v-model` numeric. |
| `base/segmented-control.vue` | `<BaseSegmentedControl>` | `options` array + `v-model`. Active item: `#9fe870` bg + ink border. ~46px height, `border-radius: 12px`. |
| `base/playing-card.vue` | `<BasePlayingCard>` | Props `rank`, `suit`, `faceDown`, `isJoker`. Red suits (♥♦) use `card-red`, black suits (♠♣) use `card-black`. `border-radius: 6px`. |
| `base/player-chip.vue` | `<BasePlayerChip>` | Props `initial`, `name`, `team: 'A' \| 'B'`, `active`. Circle initial color depends on team; inactive chips render at reduced opacity. |

Each component is presentational only — no game logic, no global state. Interactive components (`stepper`, `input`, `segmented-control`) use local `v-model` state owned by the consuming page.

## Page

`app/pages/styleguide.vue`, route `/styleguide`, not linked from any app navigation (dev-only access via direct URL).

- Page background: `canvas-soft`.
- Page title in Manrope 900 (`display` font family).
- One section per component, each with a heading (`Inter 600`, display-xs scale) + short description, wrapped in a `BaseCard` showing the component's variants/states side by side:
  - **Buttons** — all 3 variants in a row.
  - **Card** — one white example, one sage example.
  - **Badge** — `positive` / `negative` / `livre` / `obriga` in a row.
  - **Input + Stepper** — one of each, interactive (local refs).
  - **Segmented Control** — 3 options (e.g. "1"/"2"/"3" decks), state via local `ref`.
  - **Playing Card** — small grid: one black-suit card, one red-suit card, one joker, one face-down card.
  - **Player Chip** — 4 examples: team A active, team A inactive, team B active, team B inactive.
- All demo data (sample names, ranks, suits) is defined as local constants/refs inside the page — no API calls, no global store.

## Error handling & testing

No error states — this is a static/demo page with no async operations. No automated tests are added; the project has no test infrastructure yet (tracked separately in `TODO.md`). Verification is manual: run `pnpm dev` and visually inspect `/styleguide` against the design tokens.
