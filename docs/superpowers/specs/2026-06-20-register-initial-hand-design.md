# Registrar mão inicial — design

## Contexto

A tela `/games/[id]/initial-hand` hoje é um stub ("Partida criada. Registrar mão em breve."). O objetivo desta feature é implementar a tela real de registro da mão inicial (13 cartas, com duplicatas permitidas), persistir essa mão no backend, e modelar o baralho da partida como dados reais no banco — para que mais adiante, quando o estado da partida for enviado à IA, ela tenha contexto preciso do que já foi visto (mão do jogador) vs. o que ainda está "não visto" (resto do baralho).

Referência de design: `design_handoff_canastra_ai/README.md`, seção "2. Registrar mão", e o protótipo `Canastra AI - Fluxo.dc.html` (estado `isEntry`).

Escopo: apenas o jogador do device (seat_index 0, "Você") registra sua própria mão nesta tela. As mãos dos demais jogadores não são rastreadas individualmente nesta etapa.

## Backend (Laravel)

### Tabela `cards`

Nova migration `create_cards_table`:

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | uuid, PK | |
| `game_id` | uuid, FK → `games.id` | |
| `code` | string | formato `[VALOR][NAIPE]` (ex: `AS`, `7H`) ou `W` (coringão) |
| `status` | string | `deck` (default) ou `hand` |
| `player_id` | uuid nullable, FK → `players.id` | preenchido quando `status = hand` |

`Card` model: `belongsTo(Game)`, `belongsTo(Player)`. `Game` e `Player` ganham `hasMany(Card)`.

### Geração do baralho

`CreateGame` action (`app/Actions/Game/CreateGame.php`) ganha um passo `createDeck()`, chamado dentro da mesma transaction que já cria o `Game` e os `Player`s.

Para cada um dos `decks` baralhos configurados:
- 52 cartas normais: 13 ranks (`A,2,3,4,5,6,7,8,9,T,J,Q,K`) × 4 naipes (`S,H,C,D`), `status = deck`.
- 2 coringões (`code = 'W'`), `status = deck`.

Total: `54 * decks` linhas em `cards`, todas `status = deck`, `player_id = null`.

### Endpoint `GET /api/games/{game}`

Novo `GameController@show`. Retorna os dados que a tela de registro de mão precisa: `decks`, `targetScore`, e `players` (lista ordenada por `seat_index`, cada um com `id`, `seatIndex`, `name`).

- `ShowGame` action (`app/Actions/Game/ShowGame.php`): carrega `Game` com `players` ordenados.
- `GameDetailData` (`app/Data/Game/GameDetailData.php`): `id`, `decks`, `targetScore`, `players: PlayerData[]`.
- `PlayerData` (`app/Data/Player/PlayerData.php`): `id`, `seatIndex`, `name`.
- `GameDetailResource`: serializa `GameDetailData`.

O `GameResource` existente (usado só no `store`, retorna `{ id }`) não muda.

Rota: `Route::get('/games/{game}', [GameController::class, 'show']);`

### Endpoint `POST /api/players/{player}/hand`

Novo `PlayerHandController@store`. Recebe `cards: string[]` (exatamente 13 códigos) e reivindica cartas reais do baralho daquela partida.

- `StoreHandData` (`app/Data/Hand/StoreHandData.php`):
  - `cards: array` — validação de forma: `required`, `array`, `size:13`, cada item casando o regex `^(?:[2-9TJQKA][SHCD]|W)$`.
- `StorePlayerHand` action (`app/Actions/Hand/StorePlayerHand.php`), `handle(Player $player, StoreHandData $data)`, em transação:
  1. Libera de volta ao baralho (`status = 'deck'`, `player_id = null`) qualquer `Card` hoje associada a esse jogador — permite reenviar/corrigir a mão antes da confirmação final do fluxo.
  2. Agrupa os códigos pedidos por contagem (ex: `7C` × 2). Para cada código, tenta `lockForUpdate()` + reivindicar (via `limit(quantidade)`) linhas com `game_id` da partida do jogador, `code` igual, `status = 'deck'`.
  3. Se a quantidade disponível for menor que a pedida para algum código → lança `ValidationException` (422), mensagem indicando o código sem disponibilidade suficiente — isso *é* o limite por baralho (não há fórmula separada de "decks × N cópias" no backend; o limite é o estado real do baralho).
  4. Atualiza as linhas reivindicadas: `status = 'hand'`, `player_id = $player->id`.
- `Hand` não é uma entidade própria — "a mão do jogador" é só a query `Card::where('player_id', $player->id)->where('status', 'hand')`.
- `HandResource`: retorna `{ cards: string[] }` (os códigos reivindicados, na ordem recebida).

Rota: `Route::post('/players/{player}/hand', [PlayerHandController::class, 'store']);` (binding implícito por uuid, igual ao padrão já usado).

### Testes (Pest, TDD — escrever antes da implementação)

`tests/Feature/Games/ShowGameTest.php`:
- retorna decks, targetScore e jogadores ordenados por seat_index.
- 404 para jogo inexistente.

`tests/Feature/Hands/StorePlayerHandTest.php`:
- registra 13 cartas válidas e marca as linhas de `cards` correspondentes como `status=hand, player_id=<jogador>`.
- aceita duplicatas dentro do limite do baralho (ex: 2 baralhos → até 2× a mesma carta normal, até 4 coringões).
- rejeita (422) quando a quantidade pedida de um código excede o disponível no baralho daquela partida.
- rejeita (422) quando `cards` não tem exatamente 13 itens.
- rejeita (422) para código com formato inválido.
- ao reenviar uma nova mão para o mesmo jogador, libera as cartas da mão anterior de volta para `status=deck` antes de reivindicar as novas.
- não deixa um jogador reivindicar cartas que pertencem ao baralho de **outra** partida.

## Frontend (Nuxt)

### Rotas proxy (server-side, `server/api/`)

- `server/api/games/[id].get.ts` → `canastraClient()('/games/' + id)`.
- `server/api/players/[id]/hand.post.ts` → `canastraClient()('/players/' + id + '/hand', { method: 'POST', body })`.

Seguem o padrão de `server/api/games.post.ts` já existente (mesmo tratamento de erro repassando `statusCode`/`data`).

### Util `app/utils/cards.ts`

Funções puras compartilháveis pela tela:
- `RANKS`, `SUITS` (com símbolo e cor: vermelho para `H`/`D`, preto para `C`/`S`).
- `parseCard(code: string)` → `{ code, rank, suit, isJoker, label, suitSymbol, isRed }` (rank `T` exibido como `10`; coringão exibido como `W`/`★`).
- `maxCopies(code: string, decks: number)` → `decks * 2` se `code === 'W'`, senão `decks`.

### Componente `app/components/game/card-tile.vue`

Tile reutilizável para o grid de seleção e para a bandeja de selecionados.

Props: `code: string`, `count?: number` (badge, grid), `selected?: boolean`, `atLimit?: boolean`, `variant?: 'grid' | 'tray'` (tamanhos: grid 62px alto / 4 colunas; tray 34×48px).
Emite `click`. `data-testid="hand-card-{variant}-{code}"`.

### Página `app/pages/games/[id]/initial-hand.vue` (substitui o stub)

Estado local:
- `game` (resultado do `GET /api/games/:id`, carregado em `onMounted`/`useAsyncData`) → dá `decks` e `players`; `me = players.find(p => p.seatIndex === 0)`.
- `pickerSuit` (`'H' | 'D' | 'C' | 'S' | 'W'`, default `'H'`).
- `handCards: string[]` (multiset, ordem de adição).
- `submitting`, `error`, `submitted`.

Comportamento:
- Header: botão voltar (`Icon name="mdi:arrow-left"`, `data-testid="initial-hand-back"`) → `navigateTo('/games/new')`; título "Registrar mão".
- Abas de naipe (♥♦♣♠★ via `Icon`, ex: `mdi:cards-heart`/`mdi:cards-diamond`/`mdi:cards-club`/`mdi:cards-spade`/`mdi:star` — ou texto de naipe se não houver ícone direto, mantendo a cor por naipe do handoff), `data-testid="hand-suit-tab-{key}"`.
- Grid 4 colunas com `CardTile` por código do naipe ativo (13 ranks, ou só `W` na aba coringão); `count = handCards.filter(c => c === code).length`; `atLimit = count >= maxCopies(code, game.decks)`; toque adiciona 1 cópia se não estiver no limite.
- Nota de limite: "Máx. N cóp./carta · N coringões (N baralho(s))", `data-testid="hand-deck-limit-note"`.
- Bandeja fixa inferior: `CardTile` por carta em `handCards` (variant tray), toque remove 1 cópia (`removeOne`); contador `data-testid="hand-count"` mostrando `X / 13` (verde quando `X === 13`).
- CTA "Confirmar e começar" (`data-testid="confirm-hand"`), desabilitado enquanto `handCards.length !== 13` ou `submitting`.
- Submit: `POST /api/players/:meId/hand` com `{ cards: handCards }`. Erro → mensagem em `data-testid="initial-hand-error"`. Sucesso → `submitted = true`.
- Quando `submitted`, a página troca o conteúdo pela tela de confirmação simples: texto "Mão registrada!" em `data-testid="initial-hand-title"` (mesmo testid do stub atual, para não quebrar o teste e2e existente que espera essa visibilidade após a navegação).

## Testes e2e (Playwright, TDD — escrever antes da implementação)

Atualizar `e2e/tests/games-new.spec.ts`: o passo final ("aguarda navegação para a tela de mão inicial") continua validando `initial-hand-title` visível — sem mudança, já que o testid é preservado na tela de sucesso.

Novo `e2e/tests/initial-hand.spec.ts`:
- cria uma partida (reaproveitando o fluxo de `/games/new`), chega em `/games/[id]/initial-hand`.
- seleciona cartas em mais de um naipe, incluindo uma duplicata (toque 2× na mesma carta com `decks >= 2`), confirma contador parcial.
- seleciona até completar 13 cartas, confirma que `confirm-hand` fica habilitado e o contador fica verde.
- remove uma carta pela bandeja e confirma que o contador volta a refletir 12/13 e o CTA desabilita.
- completa 13 de novo, clica `confirm-hand`, espera `initial-hand-title` com o texto de sucesso.

## Fora de escopo

- Tela "Jogo contínuo" e qualquer navegação pós-sucesso além do estado inline de confirmação.
- Registro de mão dos demais jogadores (seats 1–3).
- Exposição de inventário do baralho via API (endpoint `GET` de cartas restantes) — não necessário ainda; a UI usa a fórmula `decks`/`decks*2` como dica visual, e o backend é a fonte da verdade na hora de reivindicar.
