# Regras da Canastra

Este arquivo é carregado pelo backend e enviado à IA como contexto para geração de sugestões. As regras aqui descritas são autoritativas — editar este arquivo atualiza o comportamento da IA sem alterar código.

---

## 1. Valores das Cartas

| Carta | Valor |
|---|---|
| 3, 4, 5, 6 | 0.5 pontos cada |
| 7, 8, 9, 10, J (Valete), Q (Dama), K (Rei) | 1 ponto cada |
| A (Ás) | 1.5 pontos |
| 2 (Coringuinha) | 2 pontos |
| Coringa/Joker (Coringão) | 5 pontos |

---

## 2. Canastras

Uma canastra é formada quando uma sequência (ou trinca de Ases) atinge **7 cartas**.

| Tipo | Descrição | Pontuação |
|---|---|---|
| Canastra limpa | Sem nenhum curinguinha (coringao ou sequencia pura mantem limpa)| **20 pontos** |
| Canastra suja | Contém um coringuinha (2) | **10 pontos** |

> A pontuação da canastra é somada separadamente ao valor das cartas.

---

## 3. Cartas Especiais (Curingas)

### Coringuinha (carta 2)
- O 2 pode ser usado de duas formas:
  - **Como curinga:** substitui qualquer carta em qualquer posição da sequência, independente do naipe. Neste caso **suja** a sequência.
  - **Como 2 normal:** ocupa a posição do 2 na sequência do naipe correspondente. Neste caso **não** suja a sequência.
- Máximo de **1 coringuinha por sequência** (quando usado como curinga).

### Coringão (Coringa/Joker)
- Substitui qualquer carta em qualquer posição da sequência.
- **Não suja** a canastra — a sequência permanece limpa mesmo com coringão.
- Máximo de **1 coringão por sequência**.

### Regras de coexistência de curingas

| Situação | Permitido? |
|---|---|
| Coringuinha (como curinga) + Coringão na mesma sequência | **Não** |
| 2 como valor de face + Coringão na mesma sequência | **Sim** |
| 2 como valor de face + Coringuinha (como curinga) na mesma sequência | **Sim** |
| Dois coringuinhas como curinga na mesma sequência | **Não** |
| Dois coringões na mesma sequência | **Não** |

---

## 4. Sequências

- As cartas de uma sequência devem ser do **mesmo naipe** e em **ordem consecutiva**.
- Exemplos válidos: `4♥ 5♥ 6♥`, `9♠ 10♠ J♠ Q♠`
- **Exceção:** Ases podem ser formados em **trinca** (3 ou mais Ases — podem ser de naipes diferentes).
- Para abrir uma sequência na mesa é necessário **no mínimo 3 cartas**.

---

## 5. Distribuição Inicial

- Cada jogador começa a partida com **13 cartas** na mão.

---

## 6. Abertura

- **Fora da obriga:** não há valor mínimo de pontos para abrir. Qualquer sequência válida (mínimo 3 cartas) pode ser aberta a qualquer momento.
- Abrir o jogo permite que o parceiro de dupla jogue cartas nas suas sequências — abrir cedo é estratégico.

### Obriga
- Um jogador/dupla entra em obriga quando atinge **metade da pontuação necessária para vencer a partida** (configurável).
- Em obriga, só pode abrir com **no mínimo 7.5 pontos** nas cartas abertas naquele momento.
- Enquanto em obriga e sem ter aberto ainda, o jogador **não pode jogar cartas na mesa**.

---

## 7. Lixo (Pilha de Descarte)

Apenas a carta do topo do lixo é visível. O lixo pode ser recolhido em dois casos:

**Caso 1 — Carta colocada:** a carta do topo encaixa exatamente em uma sequência que o jogador **já tem aberta na mesa** (na ponta correta, sem precisar de curinga).

**Caso 2 — Abertura nova:** a carta do topo pode ser usada para o jogador **abrir uma nova sequência válida** (mínimo 3 cartas incluindo essa carta).

Ao recolher o lixo por qualquer um dos casos acima, o jogador **pega todas as cartas do lixo** para a mão.

### Estratégia do lixo
- **Evitar cartas colocadas:** não descartar cartas que sirvam diretamente nas sequências abertas dos adversários — principalmente quando o monte está grande, pois o adversário ganharia muitas cartas.
- **Exceção:** quando prestes a bater, pode ser interessante deixar cartas colocadas para ganhar tempo ou forçar o adversário a pegar o lixo.

---

## 8. Bater (Vencer a Rodada)

- Só é possível bater se houver **ao menos uma canastra** na mesa.
- Bater = ficar sem **nenhuma carta na mão**.
- Se o jogador ainda não tem canastra, deve **segurar a última carta na mão** e continuar jogando até formar uma canastra.
- **Não há penalidade por bater sem canastra — simplesmente não é permitido.** O jogador é obrigado a manter ao menos 1 carta enquanto não tiver canastra.
- O **primeiro a bater** conclui a rodada.
- A partida tem **múltiplas rodadas** até alguém atingir a pontuação total (configurável).
- O bate contabiliza 10 pontos adicionais para a dupla vencedora, além dos pontos das cartas dos adversários.

---

## 9. Contagem de Pontos ao Final de uma Rodada

1. As cartas que os **adversários têm na mão** (não as da mesa) são somadas e adicionadas à pontuação da dupla vencedora.
2. Cartas na mesa dos adversários **não contam**.
3. Canastras formadas valem seus pontos adicionais (limpa = 20, suja = 10).
4. A soma total é **multiplicada por 10**.
5. Valores terminados em **5 são arredondados para cima**.

### Exemplo
- 3 Ases na mão dos adversários: 3 × 1.5 = 4.5 → × 10 = 45 → **50 pontos**

---

## 10. Representação das Cartas no Sistema

Formato: `[VALOR][NAIPE]`

| Valor | Código |
|---|---|
| Ás | A |
| 2 a 9 | 2–9 |
| 10 | T |
| Valete | J |
| Dama | Q |
| Rei | K |
| Coringa | W |

| Naipe | Código |
|---|---|
| Espadas | S |
| Copas | H |
| Paus | C |
| Ouros | D |

**Exemplos:** `AS` = Ás de Espadas, `7H` = 7 de Copas, `2C` = 2 de Paus, `W` = Coringa.

---

## 11. Configurações da Partida

Estes valores são configuráveis na tela de setup do jogo:

| Configuração | Padrão | Descrição |
|---|---|---|
| Número de baralhos | 2 | Quantos baralhos completos (52 cartas + 2 coringas cada) são usados |
| Pontuação para vencer | configurável | Total de pontos necessários para vencer a partida |
| Pontos da obriga | 50% da pontuação alvo | Quando se entra em obriga (metade dos pontos para vencer)
