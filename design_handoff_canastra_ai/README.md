# Handoff: Canastra AI Assistant — Mobile App

## Overview

Assistente de jogo de Canastra com sugestões geradas por IA. O usuário registra o estado da partida pela interface mobile e recebe sugestões de jogada com justificativa curta. A partida é contínua — qualquer jogada de qualquer jogador pode ser registrada, e o log da rodada cresce em tempo real.

## Sobre os arquivos de design

Os arquivos `.dc.html` neste pacote são **protótipos de design em HTML** — referências de aparência e comportamento, **não código de produção para copiar diretamente**. A tarefa é **recriar esses designs no ambiente do projeto real** (React Native, Flutter, SwiftUI ou equivalente) usando os padrões e bibliotecas já estabelecidos. Se não houver ambiente definido, React Native é a escolha recomendada dado o perfil do produto (mobile-first, iOS e Android).

Para abrir o protótipo localmente: abra `Canastra AI - Fluxo.dc.html` num navegador moderno com `support.js` no mesmo diretório.

## Fidelidade

**Alta fidelidade (hifi).** Os protótipos são mockups pixel-precisos com cores, tipografia, espaçamentos e interações finais. O desenvolvedor deve recriar a UI fielmente usando as bibliotecas e padrões do projeto.

---

## Design System: Wise

O visual segue o **Wise Design System**. Valores exatos abaixo — use-os como tokens no projeto.

### Paleta de cores

| Token | Valor | Uso |
|---|---|---|
| `--color-ink` | `#0e0f0c` | Texto principal, bordas, ícones |
| `--color-primary` | `#9fe870` | Verde lima — único acento interativo (CTAs, seleção) |
| `--color-primary-active` | `#cdffad` | Hover do CTA verde |
| `--color-primary-neutral` | `#7ac95a` | Press do CTA verde |
| `--color-primary-pale` | `#e9f9dd` | Fundo de badges positivos leves |
| `--color-canvas-soft` | `#e8ebe6` | Fundo sage — headers, seções secundárias |
| `--color-body` | `#454745` | Texto secundário |
| `--color-mute` | `#717570` | Texto terciário, placeholders |
| `--color-positive` | `#2ead4b` | Semântico positivo (nunca use verde lima para isso) |
| `--color-negative` | `#d03238` | Erros, cartas vermelhas (copas/ouros) |
| `--color-ink-deep` | `#163300` | Texto sobre fundo verde lima |
| Branco | `#ffffff` | Superfícies de cards |
| Naipes vermelhos | `#d03238` | Copas (♥) e Ouros (♦) |
| Naipes pretos | `#0e0f0c` | Espadas (♠) e Paus (♣) |

### Tipografia

| Família | Peso | Uso |
|---|---|---|
| **Manrope** | 900 apenas | Títulos display, nome do app, números de placar |
| **Inter** | 400–700 | Todo o restante (labels, body, botões, inputs) |

Tamanhos usados no protótipo:
- Título de tela: 22–28px Manrope 900
- Placar: 26–30px Manrope 900
- Corpo principal: 13–14px Inter 400
- Labels e captions: 10–12px Inter 600
- Badges uppercase: 9–10px Inter 700, `letter-spacing: 0.08–0.12em`

### Bordas e raios

| Elemento | Raio |
|---|---|
| Botões pill (CTAs) | 24px (ou `border-radius: 9999px`) |
| Cards e containers | 14–16px |
| Chips de naipe / opções | 10–12px |
| Badges de status | 9999px (pill) |
| Cartas de baralho | 5–7px |
| Inputs | 12px |

**Bordas:** `1–1.5px solid #0e0f0c` para elementos interativos; `rgba(14,15,12,0.12)` para separadores.

**Sombras:** zero. Elevação = contraste de superfície (branco sobre sage).

---

## Telas / Views

### 1. Setup — Nova partida

**Propósito:** Configurar baralhos, pontuação alvo e cadastrar jogadores na ordem da mesa.

**Layout:**
- Fundo: `--color-canvas-soft` (#e8ebe6)
- Header: logo "canastra ai" Manrope 900 22px, cor ink
- Conteúdo scrollável com `padding: 18px 24px`
- CTA fixo no rodapé com `padding: 12px 24px`

**Componentes:**

**Número de baralhos** — segmented control horizontal com 3 opções (1, 2, 3):
- Item selecionado: `background #9fe870`, borda `1.5px solid #0e0f0c`
- Item inativo: `background white`, borda `rgba(14,15,12,0.15)`
- Texto: 16px Inter 700 `#0e0f0c`
- Altura: ~46px, `border-radius: 12px`

**Pontuação para vencer** — input numérico com stepper:
- Botões − e + : `width: 48px`, fundo branco, borda `1px solid #0e0f0c`, `border-radius: 12px`
- Input central: `text-align: center`, 22px Inter 700, borda `1px solid #0e0f0c`, `border-radius: 12px`
- Ao alterar o valor: label abaixo mostra "Obriga em X pontos (metade da meta)"
- Stepper incrementa/decrementa 100 por toque

**Jogadores e ordem da mesa** — selector 2 ou 4 jogadores (pills compactos), depois lista de linhas editáveis:
- Cada linha: círculo com inicial (dupla A = verde lima, dupla B = ink), input de nome, badge de dupla, setas ▲▼ para reordenar
- Dupla A = jogadores nos índices pares (0, 2), dupla B = ímpares (1, 3)
- Badges: Dupla A = `background #e9f9dd, color #163300`; Dupla B = `background #e8ebe6, color #454745`

**CTA:** "Registrar minha mão →" — pill verde lima 24px border-radius, 15px Inter 700, largura total.

---

### 2. Registrar mão

**Propósito:** Selecionar as 13 cartas recebidas. Suporta duplicatas (múltiplas cópias da mesma carta).

**Layout:**
- Fundo: branco
- Header com botão ← + título
- Abas de naipe no topo (fixas)
- Grid de cartas (scrollável)
- Bandeja de seleção no rodapé (fixa)
- CTA fixo

**Abas de naipe** (♥ ♦ ♣ ♠ ★):
- 5 abas, flex 1 cada, `height: ~44px`, `border-radius: 10px`
- Aba ativa: `background #0e0f0c`, texto do naipe na cor correspondente (♥♦ = `#ff8a8e`, ♣♠ = branco, ★ = `#9fe870`)
- Aba inativa: `background white`, borda `rgba(14,15,12,0.15)`, cor do naipe normal (♥♦ = `#d03238`, ♣♠♠ = `#0e0f0c`)

**Grid de cartas** — 4 colunas, `gap: 8px`:
- Cada carta: `height: 62px`, `border-radius: 10px`, borda `1.5px`
- Estado normal: `background white`, borda `rgba(14,15,12,0.12)`
- Selecionada (count > 0, abaixo do limite): `background #9fe870`, borda `#0e0f0c`
- No limite máximo de cópias: `opacity: 0.28`, cursor bloqueado
- Badge de contagem no canto superior direito: `background #0e0f0c`, texto `#9fe870`, `border-radius: 9999px`, `min-width: 16px, height: 16px`
- **Lógica de duplicatas:** cada toque adiciona 1 instância da carta. Limite = `n_baralhos` por carta (coringão = `n_baralhos × 2`)
- Nota de limite no topo do grid: `"Máx. N cóp./carta · N coringões (N baralho(s))"`

**Bandeja de seleção** (rodapé fixo, fundo sage):
- Scroll horizontal das cartas selecionadas: `width: 34px, height: 48px` cada, `border-radius: 6px`
- Toque na bandeja remove 1 instância da carta
- Contador: `"X / 13 · toque p/ remover"`, cor verde `#2ead4b` quando completo, cinza `#717570` caso contrário

**Limite por naipe:** A aba Coringão (★) mostra apenas o card "W"; toque adiciona coringões respeitando o limite de `n_baralhos × 2`.

---

### 3. Jogo contínuo

**Propósito:** Tela principal da partida. Mostra estado completo + registra a jogada de qualquer jogador.

**Layout (de cima para baixo):**
1. Header sage (placar + status de obriga)
2. Faixa de vez (ink) — quem é a vez + chips dos jogadores
3. Área scrollável (recorder + log + mesa)
4. Faixa da IA (verde lima ou ink, trocável) — clicável → vai para Análise
5. Mão do jogador (só aparece quando é a vez de "Você")
6. Barra de navegação inferior (Jogo / Histórico)

**Header sage:**
- `background #e8ebe6`, `padding: 2px 22px 10px`
- Logo "canastra ai" Manrope 900 16px ink, à esquerda
- Placar "240 vs 180" Manrope 900 17px + badge de status (LIVRE / OBRIGA) à direita
- LIVRE: `background #e9f9dd, color #163300`; OBRIGA: `background rgba(208,50,56,0.12), color #d03238`

**Faixa de vez (ink):**
- `background #0e0f0c`, `padding: 10px 22px`
- Linha "Vez de [Nome]": "Vez de" em 11px `rgba(255,255,255,0.5)`, nome em Manrope 900 16px, cor verde lima se Dupla A, branco se Dupla B
- Contagem "Rodada N · jogada N" em 11px `rgba(255,255,255,0.4)`
- Chips dos jogadores em row: chip ativo tem fundo `rgba(159,232,112,0.16)`, os demais `transparent`, todos com opacity 0.5 exceto o ativo; cada chip tem círculo de inicial (verde lima para Dupla A, `#e8ebe6` para Dupla B)

**Recorder card (fundo sage, `border-radius: 16px`):**

Passo 1 — "De onde comprou":
- 2 botões: "Monte" e "Pegou o lixo"
- Selecionado: `background #9fe870`, borda `#0e0f0c`
- Não selecionado: `background white`, borda `rgba(14,15,12,0.15)`

Passo 2 — "Descartou":
- Botão dropdown abre picker inline
- Picker inline: abas de naipe (4, sem ★) + grid 7 colunas com ranks A–K
- Carta selecionada: `background #9fe870`
- Chevron ▼/▲ indica aberto/fechado

Passo 3 — "Baixou na mesa":
- 2 botões: "Não" e "Sim"
- "Não" selecionado: `background #0e0f0c, color white`
- "Sim" selecionado: `background #9fe870, color #0e0f0c`
- Se "Sim": stepper − / N carta(s) na mesa / + aparece abaixo
  - Stepper: height ~46px, borda `1.5px solid #0e0f0c`, `border-radius: 10px`, texto 18px Inter 700

CTA "Registrar e passar a vez →": pill verde lima, avanço o turnIndex, grava entrada no log.

**Log da rodada** (últimas 6 entradas):
- Cada linha: círculo de inicial + texto "{Nome} [ação]." + miniatura da carta descartada
- Miniatura: `width: 22px, height: 30px, border-radius: 4px`

**Mesa — Minhas sequências:**
- Card sage por sequência com label + badge (em formação / limpa / suja)
- Badge limpa: `background #9fe870, color #163300`
- Badge em formação: `background rgba(14,15,12,0.08), color #454745`
- Cartas: `width: 30px, height: 42px, border-radius: 6px`

**Mesa — Sequências dos adversários:** mesmo padrão, sem badge.

**Faixa da IA:**
- Vez de "Você": `background #9fe870`, título ink, subtexto `#163300`
- Vez de outro: `background #0e0f0c`, título branco, subtexto `rgba(255,255,255,0.5)`
- Clicável → navega para tela de Análise
- Conteúdo: círculo "IA" + título (sugestão principal) + subtexto (justificativa)

**Mão do jogador** (só quando `turnIndex % n === 0`, "Você"):
- Grid 6 colunas, `aspect-ratio: 5/7` por carta
- Selecionada: `background #9fe870`, borda `#0e0f0c`; normal: `background white`, borda `rgba(14,15,12,0.12)`

**Barra inferior:** 2 abas (▦ Jogo / ≣ Histórico), aba ativa em ink, inativa em mute.

---

### 4. Análise da IA

**Propósito:** Leitura estratégica completa da IA sobre a jogada recomendada.

**Layout:**
- Fundo: `#0e0f0c` (ink) do topo ao rodapé
- Status bar adapta para dark
- Header com ← (verde lima) + badge "IA · Análise completa"
- Conteúdo scrollável
- CTA fixo no rodapé

**Conteúdo:**
1. Título da jogada recomendada: Manrope 900 23px branco
2. Justificativa: 13px `rgba(255,255,255,0.55)`
3. Seção "Leitura dos adversários": título uppercase verde lima, cards por jogador (`background rgba(255,255,255,0.06), border-radius: 14px`)
   - Cada card: nome do jogador (branco 13px 700) + contagem de cartas (11px mute) + texto descritivo + chips de ação
4. Aviso de descarte: card amarelo (`background rgba(255,184,0,0.12)`, borda `rgba(255,184,0,0.3)`, título `#ffd34d`)
5. Seção "Alternativas": lista com rank (verde lima), jogada (branco 600), justificativa (branco 50%)

**CTA:** "Voltar ao jogo" — pill verde lima.

---

### 5. Histórico

**Propósito:** Visualizar histórico de partidas e rodadas com as sugestões da IA.

**Layout:**
- Header sage com título + toggle "Partidas / Rodadas"
- Toggle: pills pill com `border-radius: 9999px`, selecionado `background #0e0f0c, color white`, inativo `transparent, color #454745`
- Conteúdo scrollável
- Barra de navegação inferior

**Aba Rodadas:**
- Cards por rodada: borda `rgba(14,15,12,0.1)`, `border-radius: 14px`, `padding: 14px`
- Linha superior: título + badge de resultado
- Badges: Vitória = `rgba(46,173,75,0.15) / #2ead4b`; Derrota = `rgba(208,50,56,0.12) / #d03238`; Atual = `#9fe870 / #163300`
- Pontuação: Nós +X / Eles +X / Canastras N (16px Inter 700)
- Dica da IA: card sage com círculo "IA" + texto 11px

**Aba Partidas:**
- Cards por partida: mesmo estilo, com placar final em Manrope 900 26px

---

### 6. Fim de rodada

**Propósito:** Mostrar a contagem de pontos detalhada com arredondamento e novos totais.

**Layout:**
- Fundo: `--color-canvas-soft`
- Header com ← + título "Fim da rodada"
- Conteúdo scrollável

**Card "Bateu":** `background #0e0f0c`, `border-radius: 16px`, título em Manrope 900 22px branco.

**Card de contagem** (`background white, border-radius: 16px`):

Linhas de breakdown (borda `rgba(14,15,12,0.07)` entre elas):
- Cartas na mão dos adversários: soma dos valores das cartas
- Canastra limpa: +20 por canastra limpa
- Canastra suja: +10 por canastra suja
- Bate: +10

Linha "Subtotal em pontos": soma acima, Inter 600 15px

Linha "× 10": valor multiplicado

**Regra de arredondamento:**
```
subtotal_final = subtotal × 10
if (subtotal_final % 10 === 5) subtotal_final += 5
```
Exibir linha "Final 5 arredonda p/ cima" em verde `#2ead4b` quando houver ajuste (0 quando não houver).

Linha "Total da rodada": Manrope 900 24px, antecedida por linha dupla `2px solid #0e0f0c`.

**Totais novos:** 2 cards lado a lado
- Minha dupla: `background #9fe870`, texto ink
- Adversários: `background white`, borda implícita

**Banner de vitória** (condicional, quando total ≥ meta): card verde lima com "Partida vencida!" Manrope 900 18px.

**CTAs:** 2 botões lado a lado — "Ver histórico" (outline ink) e "Nova rodada →" (pill verde lima).

---

## Fluxo de Navegação

```
Setup
  └→ Registrar mão
       └→ Jogo (contínuo — registrar jogada de qualquer jogador, passar a vez)
            ├→ Análise da IA ←→ Jogo
            ├→ Histórico (Partidas / Rodadas) ←→ Jogo (barra inferior)
            └→ Fim de rodada
                 ├→ Histórico
                 └→ Registrar mão (nova rodada)
```

---

## Regras de Negócio Críticas

### Valores das cartas
| Carta | Valor |
|---|---|
| 3, 4, 5, 6 | 0,5 ponto cada |
| 7, 8, 9, 10, J, Q, K | 1 ponto cada |
| A (Ás) | 1,5 pontos |
| 2 (Coringuinha) | 2 pontos |
| Coringa/Joker (W) | 5 pontos |

### Canastras
- 7 cartas na mesma sequência = canastra
- Canastra limpa (sem curinga 2, coringão é permitido): +20 pontos
- Canastra suja (tem 2 como curinga): +10 pontos

### Curingas
- **Coringuinha (carta 2):** pode ser curinga (suja) ou valor de face (não suja). Máx. 1 por sequência quando usado como curinga.
- **Coringão (W/Joker):** nunca suja a canastra. Máx. 1 por sequência. Não pode coexistir com coringuinha-curinga na mesma sequência.

### Obriga
- Ativada quando o time atinge metade da pontuação alvo
- Em obriga: só pode abrir com ≥ 7,5 pontos nas cartas abertas naquela jogada

### Contagem ao fim da rodada
1. Somar valores das cartas na mão dos adversários
2. Somar canastras (+20 limpa, +10 suja)
3. Somar bate (+10)
4. Multiplicar por 10
5. Se resultado terminar em 5, arredondar para cima (+5)

### Representação de cartas no código
Formato: `[VALOR][NAIPE]`
- Valores: A 2–9 T J Q K W (T = 10, W = Coringa/Joker)
- Naipes: S (Espadas) H (Copas) C (Paus) D (Ouros)
- Exemplos: `AS` = Ás de Espadas, `TH` = 10 de Copas, `W` = Coringa

---

## State Management

### Estado global da partida
```typescript
interface GameState {
  // Setup
  decks: number;           // 1, 2 ou 3
  target: number;          // pontuação alvo (ex: 500)
  playerNames: string[];   // ordem da mesa (pares = Dupla A, ímpares = Dupla B)

  // Mão do jogador (multiset — duplicatas permitidas)
  handCards: string[];     // ex: ['JH', '7C', '7C', 'W']

  // Jogo corrente
  turnIndex: number;       // índice global de turns (% playerNames.length = jogador atual)
  roundNumber: number;
  playNumber: number;

  // Registrador de jogada
  compra: 'monte' | 'lixo' | null;
  pendingDiscard: string | null;  // código da carta descartada
  mesaLowered: boolean | null;    // null = não marcado, false = não, true = sim
  mesaCount: number;              // cartas baixadas na mesa

  // Mesa
  mySequences: Sequence[];
  oppSequences: Sequence[];

  // Log
  gameLog: PlayEntry[];

  // Histórico
  rounds: RoundRecord[];
  matches: MatchRecord[];

  // Placar
  scoreTeamA: number;
  scoreTeamB: number;
}

interface Sequence {
  label: string;
  cards: string[];    // códigos das cartas
  type: 'clean' | 'dirty' | 'forming';
}

interface PlayEntry {
  who: string;
  text: string;
  code?: string;      // carta descartada (se houver)
  hasCard: boolean;
}
```

---

## Tokens de Design (para uso direto no código)

```typescript
export const colors = {
  ink: '#0e0f0c',
  primary: '#9fe870',
  primaryActive: '#cdffad',
  primaryNeutral: '#7ac95a',
  primaryPale: '#e9f9dd',
  canvasSoft: '#e8ebe6',
  white: '#ffffff',
  body: '#454745',
  mute: '#717570',
  positive: '#2ead4b',
  negative: '#d03238',
  inkDeep: '#163300',
  cardRed: '#d03238',   // copas e ouros
  cardBlack: '#0e0f0c', // espadas e paus
  jokerBg: '#9fe870',
  jokerText: '#163300',
};

export const radii = {
  pill: 9999,
  xl: 24,
  card: 16,
  md: 12,
  sm: 8,
  cardFace: 6,
};

export const fonts = {
  display: 'Manrope',
  body: 'Inter',
};
```

---

## Assets

- **Fontes:** Manrope 900 e Inter 400/600/700 via Google Fonts (`https://fonts.googleapis.com/css2?family=Manrope:wght@900&family=Inter:wght@400;600;700&display=swap`)
- **Ícones:** Lucide Icons — sem ícones no protótipo atual, mas recomendado para implementação (`https://unpkg.com/lucide@latest`)
- **Imagens:** nenhuma — UI 100% tipográfica e geométrica

---

## Arquivos neste pacote

| Arquivo | Descrição |
|---|---|
| `README.md` | Este documento |
| `Canastra AI - Fluxo.dc.html` | Protótipo principal — 6 telas navegáveis (variante B, escolhida) |
| `Canastra AI — Exploração.dc.html` | Arquivo de exploração com 3 variações de layout lado a lado (referência) |
