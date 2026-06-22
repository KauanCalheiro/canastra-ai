<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

interface PlayerSummary { id: string, seatIndex: number, name: string, handCount: number }
interface GameSummary { id: string, decks: number, targetScore: number, turnIndex: number, discardTop: string | null, players: PlayerSummary[] }

const route = useRoute()
const gameId = route.params.id as string

const game = ref<GameSummary | null>(null)
const loadError = ref<string | null>(null)

async function loadGame() {
  try {
    game.value = await $fetch<GameSummary>(`/api/games/${gameId}`)
  } catch {
    loadError.value = 'Não foi possível carregar a partida.'
  }
}

onMounted(loadGame)

const currentPlayer = computed(() => {
  if (!game.value) return null
  const players = game.value.players
  return players[game.value.turnIndex % players.length] ?? null
})

const isTracked = computed(() => currentPlayer.value?.seatIndex === 0)

const drewFrom = ref<'monte' | 'lixo' | null>(null)
const drawnCode = ref('')
const lowered = ref<boolean | null>(null)
const loweredCount = ref(0)
const discardedCode = ref('')

const remaining = computed(() => {
  if (!currentPlayer.value) return 0
  return currentPlayer.value.handCount + 1 - loweredCount.value
})

const needsDiscard = computed(() => remaining.value > 0)
const needsDrawnCode = computed(() => drewFrom.value === 'monte' && isTracked.value)

const submitting = ref(false)
const submitError = ref<string | null>(null)

function resetForm() {
  drewFrom.value = null
  drawnCode.value = ''
  lowered.value = null
  loweredCount.value = 0
  discardedCode.value = ''
}

async function registerPlay() {
  if (!currentPlayer.value || !drewFrom.value) return
  submitError.value = null
  submitting.value = true
  try {
    await $fetch(`/api/games/${gameId}/plays`, {
      method: 'POST',
      body: {
        playerId: currentPlayer.value.id,
        drewFrom: drewFrom.value,
        drawnCode: needsDrawnCode.value ? drawnCode.value : null,
        discardedCode: needsDiscard.value ? discardedCode.value : null,
        loweredCount: loweredCount.value
      }
    })
    resetForm()
    await loadGame()
  } catch (err) {
    submitError.value = (err as { data?: { message?: string } })?.data?.message
      ?? 'Não foi possível registrar a jogada.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white">
    <header class="border-b border-ink/15 px-6 py-4">
      <h1 class="font-display font-black text-[17px] text-ink">Registrar jogada</h1>
      <p v-if="currentPlayer" class="font-body text-[13px] text-mute">
        Vez de <span class="font-semibold text-ink" data-testid="play-turn-name">{{ currentPlayer.name }}</span>
      </p>
    </header>

    <p v-if="loadError" class="px-6 py-4 font-body text-[14px] text-negative" data-testid="play-load-error">{{ loadError }}</p>

    <template v-else-if="game && currentPlayer">
      <div class="flex-1 space-y-6 overflow-y-auto px-6 py-4">
        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">1 · De onde comprou</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="drewFrom === 'monte' ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-draw-monte"
              @click="drewFrom = 'monte'"
            >Monte</button>
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="drewFrom === 'lixo' ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-draw-lixo"
              @click="drewFrom = 'lixo'"
            >Pegou o lixo</button>
          </div>
          <GameCardPicker
            v-if="needsDrawnCode"
            test-id-prefix="drawn-code"
            class="mt-3"
            :model-value="drawnCode"
            @update:model-value="(value) => (drawnCode = value as string)"
          />
        </section>

        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">2 · Baixou na mesa</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="lowered === false ? 'bg-ink text-white' : 'border-ink/15 bg-white'"
              data-testid="play-lower-no"
              @click="lowered = false; loweredCount = 0"
            >Não</button>
            <button
              type="button"
              class="flex-1 rounded-md border py-2.5 text-center font-body text-[13px] font-semibold"
              :class="lowered === true ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
              data-testid="play-lower-yes"
              @click="lowered = true; loweredCount = 1"
            >Sim</button>
          </div>
          <div v-if="lowered" class="mt-3 flex items-center gap-2">
            <button
              type="button"
              class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
              data-testid="play-lower-count-decrement"
              @click="loweredCount = Math.max(1, loweredCount - 1)"
            >−</button>
            <span class="flex-1 rounded-md border border-ink/15 px-4 py-3 text-center font-body font-bold text-[18px] text-ink" data-testid="play-lower-count-value">{{ loweredCount }}</span>
            <button
              type="button"
              class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
              data-testid="play-lower-count-increment"
              @click="loweredCount += 1"
            >+</button>
          </div>
        </section>

        <section>
          <h2 class="mb-2 font-body text-[12px] font-semibold uppercase text-mute">3 · Descartou</h2>
          <p v-if="!needsDiscard" class="font-body text-[13px] text-mute" data-testid="play-no-discard-needed">Bateu — sem descarte.</p>
          <GameCardPicker
            v-else
            test-id-prefix="discarded-code"
            :model-value="discardedCode"
            @update:model-value="(value) => (discardedCode = value as string)"
          />
        </section>
      </div>

      <p v-if="submitError" class="px-6 py-2 font-body text-[14px] text-negative" data-testid="play-error">{{ submitError }}</p>

      <footer class="border-t border-ink/15 px-6 py-3">
        <BaseButton
          variant="primary"
          class="w-full justify-center"
          data-testid="register-play"
          :disabled="!drewFrom || (needsDrawnCode && !drawnCode) || (needsDiscard && !discardedCode) || submitting"
          @click="registerPlay"
        >
          Registrar e passar a vez
          <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
        </BaseButton>
      </footer>
    </template>
  </div>
</template>
