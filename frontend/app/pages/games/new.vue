<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const deckOptions = [
  { label: '1', value: '1' },
  { label: '2', value: '2' },
  { label: '3', value: '3' }
]
const decks = ref('2')

const targetScore = ref(3000)
const obrigaScore = computed(() => Math.ceil(targetScore.value / 2))

const playerCountOptions = [
  { label: '2 jogadores', value: '2' },
  { label: '4 jogadores', value: '4' }
]
const playerCount = ref('2')
const players = ref<string[]>(['', ''])

watch(playerCount, (count) => {
  const size = Number(count)
  if (size > players.value.length) {
    players.value = [...players.value, ...Array(size - players.value.length).fill('')]
  } else {
    players.value = players.value.slice(0, size)
  }
})

function teamFor(index: number): 'A' | 'B' {
  return index % 2 === 0 ? 'A' : 'B'
}

function moveUp(index: number) {
  if (index === 0) return
  const next = [...players.value]
  ;[next[index - 1], next[index]] = [next[index], next[index - 1]]
  players.value = next
}

function moveDown(index: number) {
  if (index === players.value.length - 1) return
  const next = [...players.value]
  ;[next[index], next[index + 1]] = [next[index + 1], next[index]]
  players.value = next
}

const error = ref<string | null>(null)
const submitting = ref(false)

async function handleSubmit() {
  error.value = null
  submitting.value = true
  try {
    const { id } = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: {
        decks: Number(decks.value),
        targetScore: targetScore.value,
        players: players.value
      }
    })
    await navigateTo(`/games/${id}/initial-hand`)
  } catch (err) {
    error.value = (err as { data?: { message?: string } })?.data?.message
      ?? 'Não foi possível registrar a partida.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-canvas-soft">
    <header class="px-6 py-5">
      <h1 class="font-display font-black text-[22px] text-ink">canastra ai</h1>
    </header>

    <div class="flex-1 space-y-8 overflow-y-auto px-6 pb-32">
      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Número de baralhos</h2>
        <BaseSegmentedControl v-model="decks" :options="deckOptions" test-id="decks" />
      </section>

      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Pontuação para vencer</h2>
        <BaseStepper v-model="targetScore" :step="100" :min="100" test-id="target-score" />
        <p class="mt-2 font-body text-[13px] text-body" data-testid="obriga-score">
          Obriga em {{ obrigaScore }} pontos (metade da meta)
        </p>
      </section>

      <section>
        <h2 class="mb-3 font-body font-semibold text-[16px] text-ink">Jogadores e ordem da mesa</h2>
        <BaseSegmentedControl v-model="playerCount" :options="playerCountOptions" test-id="player-count" class="mb-4" />
        <div class="space-y-3">
          <GamePlayerRow
            v-for="(name, index) in players"
            :key="index"
            :index="index"
            :name="name"
            :team="teamFor(index)"
            :can-move-up="index > 0"
            :can-move-down="index < players.length - 1"
            @update:name="players[index] = $event"
            @move-up="moveUp(index)"
            @move-down="moveDown(index)"
          />
        </div>
      </section>

      <p v-if="error" class="font-body text-[14px] text-negative" data-testid="new-game-error">{{ error }}</p>
    </div>

    <footer class="fixed inset-x-0 bottom-0 bg-canvas-soft px-6 py-4">
      <BaseButton
        variant="primary"
        class="w-full justify-center"
        data-testid="submit-new-game"
        :disabled="submitting"
        @click="handleSubmit"
      >
        Registrar minha mão
        <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
      </BaseButton>
    </footer>
  </div>
</template>
