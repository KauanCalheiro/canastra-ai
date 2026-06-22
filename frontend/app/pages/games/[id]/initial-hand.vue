<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

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

const handCards = ref<string[]>([])

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
      <div class="flex-1 overflow-y-auto px-6 py-4">
        <GameCardPicker
          multiple
          test-id-prefix="hand"
          :decks="game.decks"
          :model-value="handCards"
          @update:model-value="(value) => (handCards = value as string[])"
        />
      </div>

      <div class="border-t border-ink/15 bg-canvas-soft px-6 py-3">
        <div class="flex items-baseline justify-between">
          <span class="font-body text-[12px] font-semibold text-ink">Minha mão</span>
          <span
            class="font-body text-[12px] font-semibold"
            :class="handComplete ? 'text-positive' : 'text-mute'"
            data-testid="hand-count"
          >{{ handCount }} / 13 · toque p/ remover</span>
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
