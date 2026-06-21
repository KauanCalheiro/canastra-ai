# Mesa e Sequências — design

## Contexto

Depois de "Registrar mão inicial" (concluído — ver `2026-06-20-register-initial-hand-design.md` e o plano correspondente), o próximo passo no fluxo do handoff (`design_handoff_canastra_ai/README.md`) é a tela "Jogo contínuo". Essa tela é grande demais pra um spec só — cobre placar/obriga, troca de turno, registrador de jogada, log da rodada, mesa com sequências, faixa da IA e navegação pra histórico. Foi decomposta em sub-projetos; este spec cobre o primeiro: **modelar a mesa e as sequências**, antes de qualquer registrador de jogada, IA, histórico ou fim de rodada.

Escopo desta etapa: **somente backend** (migrations, models, endpoints, testes Pest). Sem tela — a exibição visual da mesa entra junto quando a tela "Jogo contínuo" completa for construída.

## Decisão de produto: legalidade fica no frontend

Diferente de "Registrar mão inicial" (onde o backend é a fonte da verdade sobre o baralho), aqui a decisão foi **não** implementar a engine de validação de legalidade da sequência (mesmo naipe, ordem consecutiva, trinca de ases, mínimo 3 cartas pra abrir, limites e coexistência de curinga — ver `memory/canastra.md` seções 3 e 4) no backend. Essas regras vão ser aplicadas pela futura tela de jogo (frontend), que decide o array final de cartas+papéis antes de enviar. O backend faz só a integridade básica: você não pode colocar na mesa uma carta que a dupla não tem na mão.

Essa decisão é deliberada e foi confirmada explicitamente: o app não precisa se proteger de "injeção" de sequências maliciosas (cliente confiável, uso pessoal), então o custo de implementar e manter uma engine de validação completa não se justifica agora.

## Modelagem de dados

### Tabela `sequences` (nova)

| Coluna | Tipo |
|---|---|
| `id` | uuid, PK |
| `game_id` | uuid, FK → `games.id` |
| `team` | string (`A` \| `B`) |
| timestamps | |

Sem `suit`/`start_rank`/`is_ace_trinca` — essas colunas só fariam sentido para uma engine de legalidade, que não existe aqui.

`Sequence` model: `belongsTo(Game)`, `hasMany(Card)`.

### `Card` ganha 3 colunas novas (relevantes quando `status='table'`)

| Coluna | Tipo |
|---|---|
| `sequence_id` | uuid nullable, FK → `sequences.id` |
| `sequence_position` | int nullable — índice (0-based) da carta no array da sequência |
| `role` | string nullable (`face` \| `wild`) — armazenado exatamente como recebido na request, **sem validação** |

`Card.status` ganha um terceiro valor possível: `'table'` (além de `'deck'` e `'hand'` já existentes). `Card` ganha `belongsTo(Sequence)`.

### Status computado (não armazenado)

Dado o conjunto de `Card`s de uma `Sequence` (ordenadas por `sequence_position`):

- `forming` (em formação) se `count(cards) < 7`.
- `dirty` (suja) se `count(cards) >= 7` e existe pelo menos uma carta com `role = 'wild'` e `code != 'W'` (coringuinha usada como curinga).
- `clean` (limpa) se `count(cards) >= 7` e nenhuma carta nessa condição (coringão como curinga não suja).

## Endpoints

Ambos compartilham a mesma lógica de "substituir o estado completo da sequência":

### `POST /api/games/{game}/sequences` — criar

Body: `{ team: 'A'|'B', playerId: string, cards: [{ code: string, role: 'face'|'wild' }] }`

### `PUT /api/games/{game}/sequences/{sequence}` — substituir estado completo

Mesmo body. `{sequence}` precisa pertencer ao `{game}` da rota.

### Lógica comum (`ReplaceSequenceCards` ou equivalente)

Em uma transação:

1. **Liberar:** todas as `Card`s hoje associadas a essa `Sequence` (`sequence_id = sequence.id`) voltam para `status = 'hand'`, mantendo o `player_id` que cada uma já tinha. Não-op na criação (sequência nova não tem cartas associadas ainda).
2. **Validar pertencimento à dupla:** o `Player` referenciado por `playerId` precisa pertencer ao `game` da rota e ter `seat_index % 2` consistente com `team` (par → `A`, ímpar → `B`). Senão, 422.
3. **Reivindicar:** agrupar os códigos do array `cards` por contagem (mesmo padrão de `array_count_values` usado em `StorePlayerHand`). Para cada código, reivindicar essa quantidade de linhas `Card` com `status = 'hand'`, `code` igual, pertencentes a **qualquer jogador cujo time seja `team`** (não precisa ser exatamente o `playerId` — cobre o caso de parceiros contribuindo cartas de mãos diferentes). Se a dupla não tiver disponibilidade suficiente de algum código → 422 (rollback), mensagem indicando o código sem disponibilidade.
4. **Gravar:** as linhas reivindicadas recebem `status = 'table'`, `sequence_id = sequence.id`, `sequence_position` (= índice da carta no array `cards`, 0-based), `role` (exatamente como veio na request — sem validar contra rank/naipe).

Resposta (ambos endpoints): `SequenceResource` — `{ id, team, status: 'forming'|'clean'|'dirty', cards: [{ code, role }] }`, `cards` ordenado por `sequence_position`.

### Sem `GET` nesta etapa

Os testes verificam o estado direto via `Sequence`/`Card` models (Eloquent), sem precisar de uma rota de leitura. Um `GET /api/games/{game}/sequences` fica para quando a tela de jogo precisar consumir isso.

## O que NÃO é validado (deliberadamente)

- Naipe consistente dentro da sequência.
- Ordem/consecutividade dos ranks (ou a trinca de ases como caso especial).
- Mínimo de 3 cartas para abrir uma sequência nova.
- Máximo de 1 coringuinha-curinga e 1 coringão por sequência.
- Regras de coexistência entre coringuinha-curinga e coringão.
- Coerência entre `role` declarado e o que a carta realmente representaria numa sequência válida (ex: nada impede o cliente de marcar uma carta `face` em uma posição "errada").

Tudo isso é responsabilidade do frontend que vai consumir esses endpoints (a futura tela de jogo). O backend confia no array recebido, exceto pela integridade de posse de carta (passo 3 acima).

## Testes (Pest, TDD — escrever antes da implementação)

`tests/Feature/Sequences/CreateSequenceTest.php`:
- cria uma sequência nova com cartas da mão de um jogador, marca as `Card`s como `status='table'` com `sequence_id`/`sequence_position`/`role` corretos.
- aceita cartas vindas de mãos de jogadores diferentes da mesma dupla (parceiro contribuindo).
- calcula `status` corretamente: `forming` (<7), `clean` (>=7, só `role='wild'` com `code='W'` ou nenhum wild), `dirty` (>=7, tem `role='wild'` com `code` != `'W'`).
- rejeita (422) se o `playerId` não pertence à dupla `team` informada (paridade de `seat_index` não bate).
- rejeita (422) se a dupla não tem disponibilidade suficiente de algum código pedido (ex: pedir 2 cópias de uma carta quando só 1 está disponível na mão da dupla).
- rejeita (422) se um código pedido está em uso (mesa ou mão de outra dupla) — coberto naturalmente pela query de reivindicação ser restrita a `status='hand'` + `team` certo.

`tests/Feature/Sequences/ReplaceSequenceCardsTest.php`:
- estende uma sequência existente (PUT com array maior que o anterior) — cartas antigas continuam, novas são adicionadas, `sequence_position` recalculado pra todo o array.
- troca uma carta por outra (PUT com uma carta antiga removida e uma nova no lugar) — a carta removida volta pro `status='hand'` do jogador original; a nova é reivindicada da mão de quem a tem.
- `status` é recalculado a cada substituição (ex: sequência suja que perde o curinguinha na substituição vira limpa).
- rejeita (422) um `PUT` cujo `team` no body não bate com o `team` já gravado na sequência — `team` é imutável depois de criada.
- PUT em uma sequência de outro `game` (na URL) → 404 (sequência não pertence ao game da rota — usar implicit route-model binding escopado, ex: `$game->sequences()->findOrFail($id)` em vez de `Sequence::find($id)` direto, para que uma sequência de outro game retorne 404 em vez de ser aceita).

## Fora de escopo

- Engine de validação de legalidade (fica no frontend, futuro).
- Endpoint `GET` de sequências.
- Qualquer tela/visualização da mesa.
- Registrador de jogada (compra/descarte/baixar — turno propriamente dito).
- IA, histórico, fim de rodada.
