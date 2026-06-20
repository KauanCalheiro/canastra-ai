<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { maxCopies, RANKS, SUITS } from '~/utils/cards'

interface PlayerSummary { id: string, seatIndex: number, name: string }
interface GameSummary { id: string, decks: number, targetScore: number, players: PlayerSummary[] }

const route = useRoute()
const gameId = route.params.id as string

const game = ref<GameSummary | null>(null)
const loadError = ref<string | null>(null)

onMounted(async () => {
  try {
    game.value = await $fetch<GameSummary>(`/api/games/${gameId}`)
  } catch {
    loadError.value = 'Não foi possível carregar a partida.'
  }
})

const me = computed(() => game.value?.players.find((p) => p.seatIndex === 0) ?? null)

const suitTabs = [...SUITS, { key: 'W' as const, symbol: '★', red: false }]
const pickerSuit = ref<'H' | 'D' | 'C' | 'S' | 'W'>('H')

const pickerCodes = computed(() =>
  pickerSuit.value === 'W' ? ['W'] : RANKS.map((rank) => rank + pickerSuit.value)
)

const handCards = ref<string[]>([])

function countOf(code: string) {
  return handCards.value.filter((c) => c === code).length
}

function addCard(code: string) {
  if (!game.value) return
  if (countOf(code) >= maxCopies(code, game.value.decks)) return
  handCards.value = [...handCards.value, code]
}

function removeOne(code: string) {
  const idx = handCards.value.indexOf(code)
  if (idx === -1) return
  const next = [...handCards.value]
  next.splice(idx, 1)
  handCards.value = next
}

const deckLimitNote = computed(() => {
  if (!game.value) return ''
  const decks = game.value.decks
  return `Máx. ${decks} cóp./carta · ${decks * 2} coringões (${decks} baralho${decks > 1 ? 's' : ''})`
})

const handCount = computed(() => handCards.value.length)
const handComplete = computed(() => handCount.value === 13)

const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitted = ref(false)

async function confirmHand() {
  if (!me.value || !handComplete.value) return
  submitError.value = null
  submitting.value = true
  try {
    await $fetch(`/api/players/${me.value.id}/hand`, {
      method: 'POST',
      body: { cards: handCards.value }
    })
    submitted.value = true
  } catch (err) {
    submitError.value = (err as { data?: { message?: string } })?.data?.message
      ?? 'Não foi possível registrar a mão.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white">
    <header class="flex items-center gap-3 border-b border-ink/15 px-6 py-4">
      <button type="button" data-testid="initial-hand-back" @click="navigateTo('/games/new')">
        <Icon name="mdi:arrow-left" class="text-[20px] text-ink" />
      </button>
      <div>
        <h1 class="font-display font-black text-[17px] text-ink" data-testid="initial-hand-title">Registrar mão</h1>
        <p class="font-body text-[11px] text-mute">Toque para adicionar — toque de novo para repetir a carta</p>
      </div>
    </header>

    <p v-if="loadError" class="px-6 py-4 font-body text-[14px] text-negative" data-testid="initial-hand-load-error">{{ loadError }}</p>

    <template v-else-if="submitted">
      <div class="flex flex-1 items-center justify-center px-6 text-center">
        <p class="font-body text-[16px] text-ink" data-testid="initial-hand-success">
          Mão registrada! Aguardando o início da partida.
        </p>
      </div>
    </template>

    <template v-else-if="game">
      <div class="px-6 pt-3">
        <div class="flex gap-1.5">
          <button
            v-for="tab in suitTabs"
            :key="tab.key"
            type="button"
            class="flex-1 rounded-md border py-2.5 text-center"
            :class="pickerSuit === tab.key ? 'border-ink bg-ink' : 'border-ink/15 bg-white'"
            :data-testid="`hand-suit-tab-${tab.key}`"
            @click="pickerSuit = tab.key"
          >
            <span
              class="text-[16px] font-bold"
              :class="pickerSuit === tab.key
                ? (tab.key === 'W' ? 'text-primary' : tab.red ? 'text-[#ff8a8e]' : 'text-white')
                : (tab.red ? 'text-card-red' : 'text-card-black')"
            >{{ tab.symbol }}</span>
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-6 py-4">
        <p class="mb-2.5 text-right font-body text-[10px] text-mute" data-testid="hand-deck-limit-note">{{ deckLimitNote }}</p>
        <div class="grid grid-cols-4 gap-2">
          <GameCardTile
            v-for="code in pickerCodes"
            :key="code"
            :code="code"
            :count="countOf(code)"
            :selected="countOf(code) > 0"
            :at-limit="countOf(code) >= maxCopies(code, game.decks)"
            variant="grid"
            @click="addCard(code)"
          />
        </div>
      </div>

      <div class="border-t border-ink/15 bg-canvas-soft px-6 py-3">
        <div class="mb-2 flex items-baseline justify-between">
          <span class="font-body text-[12px] font-semibold text-ink">Minha mão</span>
          <span
            class="font-body text-[12px] font-semibold"
            :class="handComplete ? 'text-positive' : 'text-mute'"
            data-testid="hand-count"
          >{{ handCount }} / 13 · toque p/ remover</span>
        </div>
        <div class="flex min-h-[50px] items-center gap-1 overflow-x-auto">
          <span v-if="handCount === 0" class="font-body text-[12px] text-mute">Nenhuma carta ainda</span>
          <GameCardTile
            v-for="(code, index) in handCards"
            :key="`${code}-${index}`"
            :code="code"
            variant="tray"
            @click="removeOne(code)"
          />
        </div>
      </div>

      <p v-if="submitError" class="px-6 py-2 font-body text-[14px] text-negative" data-testid="initial-hand-error">{{ submitError }}</p>

      <footer class="border-t border-ink/15 px-6 py-3">
        <BaseButton
          variant="primary"
          class="w-full justify-center"
          data-testid="confirm-hand"
          :disabled="!handComplete || submitting"
          @click="confirmHand"
        >
          Confirmar e começar
          <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
        </BaseButton>
      </footer>
    </template>
  </div>
</template>
