# Mesa e Sequências — design

## Contexto

Depois de "Registrar mão inicial" (concluído — ver `2026-06-20-register-initial-hand-design.md` e o plano correspondente), o próximo passo no fluxo do handoff (`design_handoff_canastra_ai/README.md`) é a tela "Jogo contínuo". Essa tela é grande demais pra um spec só — cobre placar/obriga, troca de turno, registrador de jogada, log da rodada, mesa com sequências, faixa da IA e navegação pra histórico. Foi decomposta em sub-projetos; este spec cobre o primeiro: **modelar a mesa e as sequências**, antes de qualquer registrador de jogada, IA, histórico ou fim de rodada.

Escopo desta etapa: **somente backend** (migrations, models, endpoints, testes Pest). Sem tela — a exibição visual da mesa entra junto quando a tela "Jogo contínuo" completa for construída.

## Decisão de produto: legalidade fica no backend (e será duplicada no frontend depois)

O backend é a fonte da verdade sobre a legalidade da sequência (mesmo naipe, ordem consecutiva, trinca de ases, mínimo 3 cartas pra abrir, limites e coexistência de curinga — ver `memory/canastra.md` seções 3 e 4) — segue o mesmo princípio já usado em "Registrar mão inicial" (backend valida contra o estado real, nunca confia no cliente). Quando a tela de jogo for construída, o frontend vai duplicar essa mesma validação por UX (feedback imediato, sem round-trip), mas o backend permanece a autoridade e nunca deve aceitar uma sequência ilegal.

## Ordem de ranks

Como o `2` pode ocupar a posição de valor de face "na sequência do naipe correspondente" (regra do `memory/canastra.md`), a ordem consecutiva adotada é **Ás baixo**: `A,2,3,4,5,6,7,8,9,10(T),J,Q,K` — 13 posições, índices 0 a 12. Uma sequência normal nunca pode começar antes do índice 0 (`A`) nem terminar depois do índice 12 (`K`).

## Modelagem de dados

### Tabela `sequences` (nova)

| Coluna | Tipo |
|---|---|
| `id` | uuid, PK |
| `game_id` | uuid, FK → `games.id` |
| `team` | string (`A` \| `B`) |
| `suit` | string nullable (`S`\|`H`\|`C`\|`D`; `null` se for trinca de ases) |
| `is_ace_trinca` | bool |
| `start_rank` | string nullable (rank da posição mais baixa hoje ocupada; `null` se trinca de ases) |
| timestamps | |

`Sequence` model: `belongsTo(Game)`, `hasMany(Card)`.

### `Card` ganha 3 colunas novas (relevantes quando `status='table'`)

| Coluna | Tipo |
|---|---|
| `sequence_id` | uuid nullable, FK → `sequences.id` |
| `sequence_position` | int nullable — índice (0-based) da carta dentro da sequência |
| `role` | string nullable (`face` \| `wild`) |

`Card.status` ganha um terceiro valor possível: `'table'` (além de `'deck'` e `'hand'` já existentes). `Card` ganha `belongsTo(Sequence)`.

**`role` é derivado pelo backend, nunca declarado pelo chamador** (isso resolve a ambiguidade "2 como face vs. curinga" automaticamente — ver "Engine de legalidade" abaixo).

### Status computado (não armazenado)

Dado o conjunto de `Card`s de uma `Sequence` (ordenadas por `sequence_position`):

- `forming` (em formação) se `count(cards) < 7`.
- `dirty` (suja) se `count(cards) >= 7` e existe ao menos uma carta com `role = 'wild'` e `code` começando em `'2'` (coringuinha usada como curinga).
- `clean` (limpa) se `count(cards) >= 7` e nenhuma carta nessa condição (coringão como curinga não suja).

## Engine de legalidade

Para uma sequência normal (`is_ace_trinca = false`), com `suit` e `start_rank` fixados:

- Cada posição `i` (0-based, a partir do índice de `start_rank`) tem um **rank esperado** = rank na posição `índice(start_rank) + i` da ordem `A,2,...,K`. Estourar antes de `A` ou depois de `K` é erro.
- Pra cada carta na posição `i`:
  - se `code === 'W'` → `role = 'wild'` (coringão é sempre curinga, nunca "de face").
  - senão se `code` bate exatamente com rank-esperado + `suit` da sequência → `role = 'face'`.
  - senão se o rank do `code` é `'2'` (de qualquer naipe) → `role = 'wild'` (coringuinha usado como curinga).
  - senão → **422**, carta não combina com a posição e não é um curinga válido.
- Para trinca de ases (`is_ace_trinca = true`): todas as cartas devem ter rank `A` (naipe livre), mínimo 3, **sem curingas** (coringão e coringuinha não participam de trinca de ases).

Depois de resolver os papéis de todas as cartas da sequência (existentes + novas, quando for extensão):

- no máx. **1** carta com `role='wild'` e `code='W'` (coringão).
- no máx. **1** carta com `role='wild'` e `code` começando em `'2'` (coringuinha-curinga).
- não pode coexistir 1 coringão + 1 coringuinha-curinga na mesma sequência.
- mínimo de **3** cartas para abrir uma sequência nova.

## Endpoints

### `POST /api/games/{game}/sequences` — criar

Body: `{ playerId: string, suit: 'S'|'H'|'C'|'D'|null, startRank?: string, acesTrinca?: bool, cards: string[] }` — `cards` é a lista ordenada de códigos, da posição mais baixa pra mais alta (pra trinca de ases é só um conjunto de ≥3 ases, ordem irrelevante).

1. Determina `team` a partir de `seat_index` do `playerId` (par → `A`, ímpar → `B`).
2. Roda a engine de legalidade (acima) sobre `cards` para derivar `role` de cada uma e validar tudo (naipe/posição/trinca/mínimo 3/limites/coexistência). 422 com mensagem específica no primeiro problema encontrado.
3. **Reivindica** as cartas: agrupa `cards` por contagem (mesmo padrão `array_count_values` de `StorePlayerHand`) e reivindica essa quantidade de linhas `Card` com `status='hand'`, `code` igual, pertencentes a **qualquer jogador da dupla `team`** (cobre o parceiro contribuindo cartas da própria mão). Insuficiência de algum código → 422, rollback.
4. Cria a `Sequence` (`team`, `suit`, `is_ace_trinca`, `start_rank`); atualiza as `Card`s reivindicadas: `status='table'`, `sequence_id`, `sequence_position` (índice no array), `role` (derivado no passo 2).

### `POST /api/sequences/{sequence}/cards` — estender

Body: `{ playerId: string, cards: string[], direction: 'before'|'after' }` (`direction` ignorado se a sequência for trinca de ases — sempre acrescenta).

1. `team` = `sequence.team`; valida que `playerId` pertence a essa dupla.
2. Calcula as posições novas: `after` continua a partir da posição máxima atual +1; `before` insere antes da posição mínima atual, **deslocando `sequence_position` das cartas existentes** (+ `count(novas)`) e atualizando `start_rank` da sequência para o novo rank mais baixo.
3. Roda a engine de legalidade considerando **a sequência inteira** (cartas existentes + novas) — limites de curinga e coexistência são por sequência, não só sobre o array novo.
4. Reivindica as cartas novas do pool da dupla (mesmo mecanismo do create).
5. Persiste as novas `Card`s e, se foi `before`, atualiza `sequence_position` das cartas existentes e `start_rank` da sequência.

### `POST /api/sequences/{sequence}/cards/{position}/swap` — trocar curinga por carta real

Cobre o cenário: sequência tem `3,4,5,[2-curinga-no-lugar-do-6],7`; o jogador compra o `6` real e quer trocar o curinga pela carta real (o curinga liberado volta pra mão e pode ser jogado de novo numa extensão separada, em outra posição).

Body: `{ playerId: string, code: string }`

1. `team` = `sequence.team`; valida que `playerId` pertence a essa dupla.
2. A carta hoje na `sequence_position = {position}` precisa ter `role = 'wild'` — senão 422 (não há o que trocar).
3. `code` precisa bater **exatamente** com o rank-esperado + `suit` daquela posição (resultado do swap é sempre `role='face'`) — senão 422.
4. `code` precisa estar disponível na mão de algum jogador da dupla `team` — reivindica 1 unidade.
5. Numa transação: a carta antiga (`status='table'` → `'hand'`, `player_id = playerId` — o curinga liberado vai pra mão de quem fez a troca; `sequence_id`/`sequence_position`/`role` → `null`); a carta nova ocupa a posição (`status='table'`, `sequence_id`, `sequence_position = position`, `role='face'`).

Resposta dos três endpoints: `SequenceResource` — `{ id, team, status: 'forming'|'clean'|'dirty', cards: [{ code, role }] }`, `cards` ordenado por `sequence_position`.

### Sem `GET` nesta etapa

Os testes verificam o estado direto via `Sequence`/`Card` models (Eloquent). Um `GET /api/games/{game}/sequences` fica para quando a tela de jogo precisar consumir isso (YAGNI agora).

`{sequence}` nas rotas de estender/trocar precisa pertencer ao `{game}` implícito pela própria sequência — usar o relacionamento (`Sequence::find($id)`, sem necessidade de escopar por `game` na URL já que a sequência não muda de jogo) para essas duas rotas.

## Testes (Pest, TDD — escrever antes da implementação)

`tests/Feature/Sequences/CreateSequenceTest.php`:
- cria uma sequência normal válida (ex: `3H,4H,5H`), `Card`s corretas com `role='face'` em todas, `status` = `forming`.
- cria com coringão substituindo uma posição → `role='wild'` só nessa carta, demais `face`.
- cria com coringuinha (`2X`) em posição que não é a posição do `2` do naipe → `role='wild'`.
- cria com `2` do próprio naipe na posição correta do `2` → `role='face'` (não suja).
- cria uma trinca de ases (3+ ases de naipes variados, `suit=null`, `acesTrinca=true`).
- aceita cartas vindas da mão de jogadores diferentes da mesma dupla (parceiro contribuindo).
- canastra completa (7 cartas) sem nenhum curinguinha-curinga → `status='clean'`.
- canastra completa com 1 coringuinha-curinga → `status='dirty'`.
- canastra completa com 1 coringão (sem coringuinha) → `status='clean'`.
- rejeita (422): menos de 3 cartas.
- rejeita (422): carta que não combina com a posição nem é curinga válido.
- rejeita (422): 2 coringões na mesma sequência.
- rejeita (422): 2 coringuinhas-curinga na mesma sequência.
- rejeita (422): coringão + coringuinha-curinga coexistindo.
- rejeita (422): sequência passaria de `K` (start_rank + length - 1 > índice de K).
- rejeita (422): `playerId` não pertence à dupla `team` resolvida.
- rejeita (422): dupla não tem disponibilidade suficiente de algum código pedido.
- rejeita (422): trinca de ases com carta que não é Ás, ou com curinga.

`tests/Feature/Sequences/ExtendSequenceTest.php`:
- estende com `direction='after'`, continua a contagem de posições e o cálculo de `status`.
- estende com `direction='before'`, desloca `sequence_position` das cartas existentes e atualiza `start_rank`.
- estende uma trinca de ases (ignora `direction`, só acrescenta ases).
- rejeita (422) extensão que violaria limite de curinga considerando a sequência inteira (existente + nova).
- rejeita (422) extensão que passaria de `K` ou voltaria antes de `A`.
- rejeita (422) `playerId` de dupla diferente da sequência.

`tests/Feature/Sequences/SwapSequenceCardTest.php`:
- troca um coringão por carta real numa posição — carta antiga volta pra mão de quem trocou, `status` pode virar `clean` se isso zera o último curinga sujo (cenário com coringuinha também presente) ou continuar `clean`.
- troca um coringuinha-curinga por carta real — `status` pode virar de `dirty` para `clean`.
- rejeita (422) troca numa posição que já é `role='face'` (nada pra trocar).
- rejeita (422) `code` que não bate com o rank/naipe esperado na posição.
- rejeita (422) `code` indisponível na mão da dupla.

## Fora de escopo

- Endpoint `GET` de sequências.
- Qualquer tela/visualização da mesa.
- Registrador de jogada (compra/descarte/baixar — turno propriamente dito).
- IA, histórico, fim de rodada.
- Duplicar a validação no frontend (entra junto com a tela de jogo, fora deste recorte).
