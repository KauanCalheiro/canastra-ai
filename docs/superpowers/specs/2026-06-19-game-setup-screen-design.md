# Tela de Setup — Nova Partida — design

## Context

Primeira tela "real" do fluxo de jogo (`Setup → Registrar mão → Jogo → ...`), descrita em detalhe em `design_handoff_canastra_ai/README.md` (seção "1. Setup — Nova partida"). Até agora o projeto só tem a `/styleguide` com os componentes base; esta é a primeira tela funcional e o primeiro backend do projeto (até aqui só havia frontend Nuxt sem `server/`).

Fonte de verdade de regras de negócio: `RULES.md` (baralhos, pontuação, obriga). Fonte de verdade visual: `design_handoff_canastra_ai/README.md`.

## Arquitetura

- **Rota da tela:** `/games/new` → `app/pages/games/new.vue`.
- **Rota de destino após submit:** `/games/[id]/initial-hand` → `app/pages/games/[id]/initial-hand.vue`, página placeholder mínima ("Em breve" + id da partida) — a tela de Registrar mão será desenhada num próximo ciclo.
- **API:** `POST /api/games`, Nitro server route em `server/api/games.post.ts`. Recebe o payload, valida, grava no SQLite, retorna `{ id }`.
- **Banco:** SQLite via `better-sqlite3`. Conexão singleton em `server/utils/db.ts`, caminho do arquivo configurável via env var `DB_PATH` (default `.data/canastra.sqlite`; pasta `.data/` entra no `.gitignore`). Tabelas criadas com `CREATE TABLE IF NOT EXISTS` na primeira chamada ao módulo — sem migration framework por agora (schema pequeno, sem ORM).
- **IDs:** uuid v4 via lib `uuid` (nova dependência), gerados no server antes do insert.

## Schema

```sql
CREATE TABLE games (
  id TEXT PRIMARY KEY,           -- uuid v4
  decks INTEGER NOT NULL,
  target_score INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE players (
  id TEXT PRIMARY KEY,           -- uuid v4
  game_id TEXT NOT NULL REFERENCES games(id),
  seat_index INTEGER NOT NULL,   -- ordem da mesa, 0-based
  name TEXT NOT NULL DEFAULT ''
);
```

A dupla (A/B) é derivada de `seat_index % 2` (par = Dupla A, ímpar = Dupla B) — não é armazenada, evita inconsistência se a ordem for reorganizada depois.

`POST /api/games` recebe:
```typescript
{ decks: 1 | 2 | 3, targetScore: number, players: string[] }  // players.length === 2 || 4
```
Insere 1 linha em `games` + N linhas em `players` numa transação (`db.transaction(...)` do better-sqlite3). Retorna `{ id: string }`.

## UI da tela (`/games/new`)

Segue `design_handoff_canastra_ai/README.md` seção 1:

- Fundo `canvas-soft`; header "canastra ai" (Manrope 900, 22px, ink); conteúdo scrollável com `padding: 18px 24px`; CTA fixo no rodapé.
- **Número de baralhos** — `BaseSegmentedControl` (já existe), opções `1`/`2`/`3`, default `'2'`.
- **Pontuação para vencer** — `BaseStepper` (já existe, `step={100}`), mínimo `100` (decrementar abaixo é bloqueado no componente — ver "Mudanças em componentes existentes"), default `3000`. Abaixo do stepper, label reativa: `"Obriga em {Math.ceil(targetScore / 2)} pontos (metade da meta)"`.
- **Jogadores e ordem da mesa:**
  - Seletor de quantidade (pills `2` / `4`) acima da lista. Trocar de 4→2 descarta as 2 últimas linhas; de 2→4 adiciona 2 linhas vazias.
  - Lista de `app/components/game/player-row.vue` (novo componente, específico desta tela — não é um átomo do design system, por isso fica em `game/` e não em `base/`):
    - Círculo com inicial do nome (1ª letra, ou vazio se nome vazio), cor por dupla.
    - `BaseInput` para o nome (vazio permitido, sem validação).
    - Badge de dupla via `BaseBadge` (ver variants novos abaixo).
    - Setas ▲▼ para reordenar — trocam `seat_index` com a linha adjacente.
- **CTA "Registrar minha mão →"** — `BaseButton` variant primary, sempre habilitado. Ao clicar:
  1. `POST /api/games` com o estado atual do formulário.
  2. Sucesso → `navigateTo('/games/' + id + '/initial-hand')`.
  3. Erro → exibe a mensagem retornada pela API numa linha de texto acima do CTA (`text-negative`), mantém o formulário preenchido, não navega.

### Mudanças em componentes existentes

- `app/components/base/stepper.vue`: adicionar prop `min` (default `undefined` = sem mínimo); `decrement()` não emite se `modelValue - step < min`. Usado aqui com `:min="100"`.
- `app/components/base/badge.vue`: adicionar variants `'team-a'` e `'team-b'`:
  - `team-a` → `bg-primary-pale text-ink-deep` (mesma cor já usada por `positive`/`livre` — reaproveita a classe existente).
  - `team-b` → `bg-canvas-soft text-body` (novo par de cores, exato do handoff).

## Validação e tratamento de erros

- **Client-side:** decks ∈ {1,2,3} e players.length ∈ {2,4} são garantidos pelos próprios componentes de seleção (não há estado inválido alcançável pela UI). Target score ≥ 100 é garantido pelo `min` do stepper. Nomes podem ficar vazios.
- **Server-side (`POST /api/games`):** revalida tudo — nunca confia no client:
  - `decks` deve ser `1`, `2` ou `3` → senão `400`.
  - `targetScore` deve ser inteiro ≥ 100 → senão `400`.
  - `players` deve ser array de string com length `2` ou `4` → senão `400`.
  - Falha ao gravar no SQLite → `500`.
- Mensagens de erro em português, devolvidas no corpo (`{ error: string }`), exibidas inline na tela.

## Testes (TDD — testes escritos antes da implementação)

Infraestrutura de teste nova para o projeto (hoje há zero testes automatizados, rastreado em `TODO.md`).

### API — Vitest + `@nuxt/test-utils/e2e`

- `tests/api/games.post.test.ts`.
- `server/utils/db.ts` lê o caminho do SQLite de `process.env.DB_PATH` (default `.data/canastra.sqlite`).
- `setup()` do `@nuxt/test-utils/e2e` sobe o Nitro real; antes da suíte, `DB_PATH` é apontado para um arquivo em `os.tmpdir()` com nome único (uuid), apagado no teardown — nunca toca no banco de desenvolvimento.
- Casos:
  - Cria partida com 2 jogadores → `200`, retorna `id` (uuid), linhas gravadas em `games` e `players`.
  - Cria partida com 4 jogadores → idem, 4 linhas em `players` com `seat_index` 0–3.
  - `decks` inválido (ex: `5`) → `400`.
  - `targetScore` abaixo de 100 → `400`.
  - `players.length` fora de `{2,4}` (ex: 3) → `400`.
- Script `package.json`: `"test:api": "vitest run tests/api"`.

### Frontend — Playwright headless

- `tests/e2e/games-new.spec.ts`, `@playwright/test` (nova dependência).
- `playwright.config.ts` com `webServer` apontando para `pnpm dev` (ou `preview`), `baseURL: http://localhost:3000`.
- Caso: abrir `/games/new`, preencher 2 nomes de jogador, alterar baralhos para `3`, clicar "Registrar minha mão →", esperar `page.waitForURL` casando `/games/[uuid]/initial-hand`.
- Script `package.json`: `"test:e2e": "playwright test"`.

### Fluxo TDD

Para cada unidade de comportamento (validação da API, cada caso de erro, o fluxo de submit da tela): escrever o teste primeiro (deve falhar), então implementar o mínimo necessário para passar, depois repetir para o próximo caso.

## Fora de escopo

- Tela `/games/[id]/initial-hand` real (fica como placeholder).
- Estado global (Pinia/store) — não introduzido nesta etapa; a tela só lê/escreve estado local até o submit.
- Edição/listagem de partidas existentes.
- Autenticação/usuários.
