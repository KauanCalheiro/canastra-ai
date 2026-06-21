# Mesa e Sequências — rascunho (brainstorming em andamento)

> Sessão interrompida — usuário precisou saudar. Retomar a partir de "Próximo passo" no final do arquivo.

## Contexto

Depois de "Registrar mão inicial" (concluído, ver `2026-06-20-register-initial-hand-design.md` e plano correspondente), o próximo passo no fluxo do handoff é a tela "Jogo contínuo". Essa tela é grande demais pra um spec só (cobre placar/obriga, troca de turno, registrador de jogada, log, mesa com sequências, faixa da IA, nav pra histórico) — foi decomposta em sub-projetos.

**Sub-projeto escolhido primeiro: Mesa e Sequências** (modelar sequências na mesa — antes do registrador de jogada, antes da IA, antes de histórico/fim de rodada).

## Decisões já tomadas (via perguntas ao usuário)

1. **Posse da sequência:** por **dupla** (`team: 'A'|'B'`), não por jogador individual — parceiros podem baixar na mesma sequência. Time = `seat_index % 2 === 0 ? 'A' : 'B'`, mesma convenção já usada em `frontend/app/pages/games/new.vue` (`teamFor`).
2. **Criação de sequências:** via **endpoint dedicado** (não seed/factory) — `POST` que recebe dupla + cartas, calcula limpa/suja/canastra/em-formação. Cartas vêm da **mão do jogador que está jogando** (`Card.status = 'hand'`), são reivindicadas e passam para `Card.status = 'table'`.
3. **Estender sequência existente:** **sim**, suportar desde já — endpoint separado para adicionar cartas a uma sequência já aberta da mesma dupla (não só criar novas).
4. **Validação de legalidade:** **completa** — mesmo naipe + ordem consecutiva (ou trinca de ases), máx. 1 coringuinha-como-curinga e 1 coringão por sequência, regras de coexistência (tabela da seção 3 de `memory/canastra.md`), mínimo 3 cartas pra abrir.
5. **Frontend:** **nenhum nesta etapa** — só backend (migration/model/validação/endpoints/testes Pest). A exibição visual da mesa entra junto quando a tela "Jogo contínuo" completa for construída.

## Arquitetura (apresentada e aprovada pelo usuário)

Estender o modelo `Card` (que já rastreia `status: 'deck'|'hand'`) com um terceiro estado, `'table'`. Uma carta na mesa pertence a uma `Sequence` (nova tabela), que pertence a uma dupla (`team: 'A'|'B'`), não a um jogador. Dois endpoints: criar sequência nova (abrir, mínimo 3 cartas) e estender uma existente. Toda a legalidade é validada no backend antes de mover qualquer carta da mão pra mesa — tudo em uma transação, seguindo o mesmo padrão de "reivindicar cartas reais" já usado em `StorePlayerHand` (Task 4 do plano anterior).

## Rascunho de modelagem (NÃO confirmado com o usuário ainda — retomar daqui)

Pensado durante a sessão, mas ainda precisa ser apresentado e validado:

### Ordem de ranks

Como o `2` pode ocupar posição de valor de face "na sequência do naipe correspondente" (regra do `memory/canastra.md`), a ordem consecutiva adotada é **Ás baixo**: `A,2,3,4,5,6,7,8,9,10(T),J,Q,K` (13 posições possíveis, Ás não é alto).

### Tabela `sequences`

| Coluna | Tipo |
|---|---|
| `id` | uuid PK |
| `game_id` | FK |
| `team` | string (`A`\|`B`) |
| `suit` | string nullable (`S`\|`H`\|`C`\|`D`; null se trinca de ases) |
| `is_ace_trinca` | bool |
| `start_rank` | string nullable (rank da posição mais baixa hoje ocupada; null se trinca de ases) |
| timestamps | |

Status (`em formação`/`limpa`/`suja`) **não é coluna** — computado a partir das cartas associadas (tamanho + papéis), do mesmo jeito que hoje resolvemos a mão a partir de `Card.status`.

### `Card` ganha (para status `table`)

- `sequence_id` (FK nullable)
- `sequence_position` (int — posição ordinal na sequência; irrelevante/arbitrário pra trinca de ases)
- `role` (string: `face`\|`wild` — papel que a carta ocupa naquela posição)

`role` é **derivado pelo backend na hora de validar/gravar**, não declarado pelo chamador: dado `suit` + `start_rank` da sequência, a posição `i` espera rank = `start_rank + i`; se o código da carta bate exatamente com esse rank+naipe → `face`; senão precisa ser `W` (coringão, sempre wild) ou um `2` de qualquer naipe (coringuinha como curinga) — qualquer outra carta não-combinante é inválida. Isso resolve sozinho a ambiguidade "2 como face vs. curinga" sem o chamador precisar declarar.

### Endpoints propostos (ainda não validados com o usuário)

- `POST /api/games/{game}/sequences` — cria sequência nova.
  Body: `{ playerId, suit: 'S'|'H'|'C'|'D'|null, startRank?: string, acesTrinca?: bool, cards: string[] }` (`cards` ordenado da posição mais baixa pra mais alta; pra trinca de ases é só um conjunto de >=3 ases).
  Valida: jogador pertence ao game; cartas estão na mão do jogador (`status=hand`); mínimo 3 cartas; legalidade de naipe/sequência/trinca; limites e coexistência de curingas.
  Efeito: cria `Sequence`; move as `Card`s reivindicadas pra `status='table'`, preenchendo `sequence_id`/`sequence_position`/`role`; `player_id` da carta continua sendo quem jogou (rastro pro log futuro), mas a posse "de regra" é da `Sequence.team`.

- `POST /api/sequences/{sequence}/cards` — estende sequência existente.
  Body: `{ playerId, cards: string[], direction: 'before'|'after' }` (direção explícita pra não precisar inferir; trinca de ases ignora `direction`).
  Valida: time do jogador bate com `sequence.team`; cartas na mão do jogador; extensão contígua sem passar de A..K; limites de curinga considerando as cartas **já existentes** na sequência + as novas.

- (Talvez) `GET /api/games/{game}/sequences` — listar sequências do jogo, pro futuro consumo da tela "Jogo contínuo"/IA. Ainda não decidido se entra neste recorte ou só quando a tela existir.

### Pontos que ainda precisam ser discutidos/confirmados com o usuário

1. Confirmar a modelagem acima (tabela `sequences`, extensão de `Card`, endpoints) — **nada disso foi mostrado ao usuário ainda**, é só rascunho interno.
2. Confirmar regra de ordem de ranks (Ás baixo) — não foi perguntado explicitamente, foi uma decisão de design que precisa validação.
3. Decidir se `GET /sequences` entra neste recorte ou fica para depois.
4. Definir mensagens de erro exatas pra cada violação (naipe errado, não-consecutivo, mais de 1 coringuinha-curinga, mais de 1 coringão, coexistência proibida, menos de 3 cartas pra abrir, ultrapassar K ou voltar antes do A na extensão).
5. Depois de aprovar o desenho: escrever o spec formal em `docs/superpowers/specs/`, autorevisão, pedir revisão do usuário, e então `superpowers:writing-plans` pro plano de implementação (TDD primeiro, mesmo de sempre).

## Próximo passo

Retomar apresentando a seção "Modelagem de dados" (tabela `sequences` + extensão de `Card`) pro usuário, seguida de "Validação de legalidade" e "Endpoints" — uma seção por vez, pedindo confirmação a cada uma, como already fizemos com a seção "Arquitetura" (já aprovada). Depois disso, seguir o checklist normal do skill `superpowers:brainstorming` (escrever spec formal, autorevisão, revisão do usuário, `writing-plans`).
