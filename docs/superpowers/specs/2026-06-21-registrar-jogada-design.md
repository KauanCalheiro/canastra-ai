# Registrar Jogada + Avançar Turno — design

## Contexto

Depois de "Mesa e Sequências" (concluído — ver `2026-06-21-mesa-sequencias-design.md` e plano correspondente), este é o segundo sub-projeto da tela "Jogo contínuo" (decomposta em `2026-06-21-mesa-sequencias-design.md`'s contexto). Cobre o **núcleo do turno**: de onde o jogador comprou, o que descartou, quantas cartas baixou (só contagem, sem integrar com sequências reais ainda), e o avanço pro próximo jogador — com uma tela mínima pra registrar isso.

## Decisão central: jogador "rastreado" vs "não-rastreado"

Só o jogador do seat 0 ("Você", o dono do device) tem mão individualmente rastreada (`Card.status='hand'` com `player_id` real, via "Registrar mão inicial"). Os outros 3 jogadores nunca tiveram cartas identificadas — de propósito, já que na vida real você não vê a mão deles.

Isso exige tratamento diferente por tipo de jogador:

- **Rastreado (seat 0):** compra do monte exige o código exato (`drawnCode`, o humano vê sua própria carta) — reivindica uma `Card` real `status='deck'` → `status='hand'`. Descarte exige o código exato, reivindicado da **própria mão rastreada** (`status='hand', player_id=ele`) → `status='discard'`.
- **Não-rastreado (qualquer outro):** compra do monte é cega — ninguém vê, nem o humano. Não move nenhuma `Card`; é só `hand_count + 1` (a carta comprada continua sendo uma das cartas anônimas em `status='deck'`, já que pra nós "baralho restante" e "mãos não-rastreadas" são o mesmo pool indiferenciado). Descarte **é visível** (qualquer um vê o que foi jogado), então o código revelado reivindica do pool `status='deck'` direto (nunca de `hand`, que não existe pra ele) → `status='discard'`.
- **Pegar o lixo (qualquer jogador):** a carta do topo do lixo já tem código conhecido no banco (não precisa o humano re-informar). Rastreado: vai para a mão dele (`status='hand'`, `player_id` dele). Não-rastreado: volta a ser anônima (`status='deck'`, sem `player_id`) — ela estava visível, mas ao entrar numa mão não-rastreada perde a identificação de novo.
- **Baixar na mesa:** nesta etapa é **só uma contagem** (`hand_count -= loweredCount`) — não cria/estende `Sequence` nem move `Card`s, pra qualquer jogador (inclusive o rastreado). Integrar de fato com `CreateSequence`/`ExtendSequence` fica para um recorte futuro (vai exigir resolver a reivindicação de baralho pra jogador não-rastreado também).

## Regra de descarte obrigatório

Depois de aplicar compra (+1) e baixar-na-mesa (−`loweredCount`), seja `remaining = hand_count + 1 - loweredCount`:

- Se `remaining > 0`: `discardedCode` é **obrigatório**. Ausente → `DiscardRequiredException`.
- Se `remaining === 0` (bateu): `discardedCode` deve ser **nulo**. Presente → `NothingToDiscardException` (não há carta pra descartar).
- Se `remaining < 0` (i.e. `loweredCount > hand_count + 1`): `LoweredCountExceedsHandException`.

Validação de obriga/canastra pra poder bater **não é verificada** nesta etapa (fora de escopo — não há rastreamento de placar/canastra suficiente ainda).

## Modelagem de dados

### `games` ganha `turn_index`

`unsignedInteger('turn_index')->default(0)`. Jogador atual = jogadores do game ordenados por `seat_index`, índice `turn_index % count(jogadores)`.

### `players` ganha `hand_count`

`unsignedInteger('hand_count')->default(13)` — inicializado em 13 pra **todos** os jogadores na criação do jogo (`CreateGame`), independente de serem rastreados ou não (a regra "cada jogador começa com 13 cartas" é invariante).

### `cards.status` ganha o valor `'discard'`

`Card` ganha `discard_position` (`unsignedInteger nullable`) — topo do lixo = maior `discard_position` daquele `game_id` com `status='discard'`. Ao reivindicar o topo do lixo, `discard_position` volta a `null`.

### Tabela `plays` (nova) — log da jogada

| Coluna | Tipo |
|---|---|
| `id` | uuid, PK |
| `game_id` | uuid, FK → `games.id` |
| `player_id` | uuid, FK → `players.id` |
| `turn_index` | unsignedInteger — valor de `games.turn_index` **antes** do incremento desta jogada |
| `drew_from` | string (`monte`\|`lixo`) |
| `discarded_code` | string nullable |
| `lowered_count` | unsignedInteger, default 0 |
| timestamps | |

`Play` model: `belongsTo(Game)`, `belongsTo(Player)`.

## Endpoint

### `POST /api/games/{game}/plays` — registrar jogada e avançar turno

Body: `{ playerId: string, drewFrom: 'monte'|'lixo', drawnCode?: string, discardedCode?: string, loweredCount?: int }` (`loweredCount` default 0).

Lógica (`RegisterPlay` action), numa transação:

1. Resolve jogadores do game ordenados por `seat_index`; jogador atual = `players[turn_index % count]`. Se `playerId` não bate com o atual → `NotPlayersTurnException`.
2. `isTracked = player.seat_index === 0`.
3. **Compra:**
   - `drewFrom='monte'` e `isTracked`: exige `drawnCode` (ausente → `DrawnCodeRequiredException`); reivindica 1 `Card` (`game_id`, `code=drawnCode`, `status='deck'`, `lockForUpdate`) → `status='hand'`, `player_id=player`. Não encontrada → `InsufficientCardsInPoolException(drawnCode, 1, 0)`.
   - `drewFrom='monte'` e não-rastreado: nenhuma mutação de `Card`.
   - `drewFrom='lixo'`: busca o topo do lixo (`Card` `game_id`, `status='discard'`, maior `discard_position`). Vazio → `DiscardPileEmptyException`. Rastreado: vira `status='hand'`, `player_id=player`, `discard_position=null`. Não-rastreado: vira `status='deck'`, `player_id=null`, `discard_position=null`.
4. `remaining = player.hand_count + 1 - loweredCount`. `remaining < 0` → `LoweredCountExceedsHandException`.
5. **Descarte:**
   - `remaining === 0`: `discardedCode` presente → `NothingToDiscardException`. `hand_count` final = 0.
   - `remaining > 0`: `discardedCode` ausente → `DiscardRequiredException`. Senão:
     - Rastreado: reivindica da própria mão (`status='hand'`, `player_id=player`, `code=discardedCode`) → `status='discard'`, `discard_position` = maior atual do game + 1, `player_id` permanece `player` (quem descartou). Indisponível → `InsufficientCardsInPoolException`.
     - Não-rastreado: reivindica do pool `deck` (`status='deck'`, `code=discardedCode`, `game_id`) → `status='discard'`, `discard_position` = maior+1, `player_id=player`. Indisponível → `InsufficientCardsInPoolException`.
     - `hand_count` final = `remaining - 1`.
6. Atualiza `player.hand_count`.
7. Cria `Play` (`game_id`, `player_id`, `turn_index` = valor **antes** do incremento, `drew_from`, `discarded_code`, `lowered_count`).
8. Incrementa `game.turn_index += 1`.

Resposta (`PlayResource`): `{ id, playerId, turnIndex, drewFrom, discardedCode, loweredCount, handCountAfter, nextPlayerId }` — `nextPlayerId` é o jogador cujo turno começa agora (`players[(turnIndex+1) % count].id`).

### Extensão de `GET /api/games/{game}`

`GameDetailResource` ganha `turnIndex` (de `game.turn_index`) e cada jogador em `players[]` ganha `handCount`. Resposta ganha também `discardTop` (`{ code }` da carta no topo do lixo, ou `null` se não houver nenhuma).

## Novas exceptions

| Exception | `errorCode()` |
|---|---|
| `NotPlayersTurnException` | `not_players_turn` |
| `DrawnCodeRequiredException` | `drawn_code_required` |
| `DiscardPileEmptyException` | `discard_pile_empty` |
| `DiscardRequiredException` | `discard_required` |
| `NothingToDiscardException` | `nothing_to_discard` |
| `LoweredCountExceedsHandException` | `lowered_count_exceeds_hand` |

(`InsufficientCardsInPoolException` já existe e é reaproveitada.)

## Frontend

### `GameCardPicker` (novo componente, extraído de `initial-hand.vue`)

Generaliza o seletor de abas-de-naipe + grid já construído. Prop `multiple?: boolean` (default `false`):

- `multiple=true`: `modelValue: string[]`, comportamento idêntico ao de hoje (tray, contagem por carta, limite por `decks`).
- `multiple=false`: `modelValue: string`, clique no grid **substitui** o valor (não acumula), sem tray, sem contagem/limite.

`initial-hand.vue` é refatorado para usar `<GameCardPicker multiple v-model="handCards" :decks="game.decks" />` no lugar da lógica inline de abas+grid — comportamento visível idêntico, só reorganização.

### Tela de registrar jogada (nova)

Rota `/games/[id]/play` — acessível diretamente por ora (não há ainda uma tela "Jogo contínuo" completa pra navegar a partir dela; a tela de mão inicial não navega automaticamente pra esta, fica pra quando o fluxo completo existir).

- Busca `GET /api/games/:id` no mount; mostra de quem é a vez (nome do jogador atual, via `turnIndex`).
- Passo 1 — De onde comprou: botões "Monte" / "Pegou o lixo". Se "Monte" e o jogador da vez for o seat 0: mostra `<GameCardPicker v-model="drawnCode" />`.
- Passo 2 — Baixou na mesa: "Não"/"Sim"; se "Sim", stepper de contagem (`loweredCount`).
- Passo 3 — Descartou: calculado (`hand_count + 1 - loweredCount`); se `> 0`, mostra `<GameCardPicker v-model="discardedCode" />` (obrigatório); se `=== 0`, não mostra nada (bateu).
- CTA "Registrar e passar a vez" → `POST /api/games/:id/plays`; em sucesso, refaz o fetch do estado e atualiza a tela pro próximo jogador.

## Testes

Backend (Pest, TDD): `tests/Feature/Plays/RegisterPlayTest.php` cobrindo compra monte/lixo rastreado e não-rastreado, descarte obrigatório/bate, todas as exceptions da tabela acima, avanço de `turn_index`, atualização de `hand_count`. `tests/Feature/Games/ShowGameTest.php` ganha asserções para `turnIndex`/`handCount`/`discardTop`.

Frontend (Playwright, TDD): novo `e2e/tests/register-play.spec.ts` cobrindo o fluxo feliz (seat 0 compra do monte com código, baixa 0, descarta, avança turno) e o caso de bater (baixa tudo, sem descarte).

## Fora de escopo

- Recolher o lixo inteiro (só o topo, uma carta por vez).
- Integração real de "baixar na mesa" com `CreateSequence`/`ExtendSequence` (fica contagem-only).
- Validação de obriga/canastra para permitir bater.
- IA, histórico, fim de rodada, placar.
