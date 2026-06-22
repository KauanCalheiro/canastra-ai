<script setup lang="ts">
import { computed, ref } from 'vue'
import { maxCopies, RANKS, SUITS } from '~/utils/cards'

const props = withDefaults(defineProps<{
  modelValue: string | string[]
  multiple?: boolean
  decks?: number
  testIdPrefix?: string
}>(), {
  multiple: false,
  decks: 1,
  testIdPrefix: 'card-picker'
})

const emit = defineEmits<{ 'update:modelValue': [value: string | string[]] }>()

const suitTabs = [...SUITS, { key: 'W' as const, symbol: '★', red: false }]
const pickerSuit = ref<'H' | 'D' | 'C' | 'S' | 'W'>('H')

const pickerCodes = computed(() =>
  pickerSuit.value === 'W' ? ['W'] : RANKS.map((rank) => rank + pickerSuit.value)
)

const multiValue = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []))
const singleValue = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : ''))

function countOf(code: string) {
  return multiValue.value.filter((c) => c === code).length
}

function selectGridCard(code: string) {
  if (props.multiple) {
    if (countOf(code) >= maxCopies(code, props.decks)) return
    emit('update:modelValue', [...multiValue.value, code])
  } else {
    emit('update:modelValue', code)
  }
}

function removeFromTray(code: string) {
  const idx = multiValue.value.indexOf(code)
  if (idx === -1) return
  const next = [...multiValue.value]
  next.splice(idx, 1)
  emit('update:modelValue', next)
}

const deckLimitNote = computed(() =>
  `Máx. ${props.decks} cóp./carta · ${props.decks * 2} coringões (${props.decks} baralho${props.decks > 1 ? 's' : ''})`
)

const tileTestIdPrefix = computed(() => `${props.testIdPrefix}-card`)
</script>

<template>
  <div>
    <div class="flex gap-1.5">
      <button
        v-for="tab in suitTabs"
        :key="tab.key"
        type="button"
        class="flex-1 rounded-md border py-2.5 text-center"
        :class="pickerSuit === tab.key ? 'border-ink bg-ink' : 'border-ink/15 bg-white'"
        :data-testid="`${testIdPrefix}-suit-tab-${tab.key}`"
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

    <p v-if="multiple" class="mb-2.5 mt-3 text-right font-body text-[10px] text-mute" :data-testid="`${testIdPrefix}-deck-limit-note`">{{ deckLimitNote }}</p>

    <div class="mt-3 grid grid-cols-4 gap-2">
      <GameCardTile
        v-for="code in pickerCodes"
        :key="code"
        :code="code"
        :count="multiple ? countOf(code) : 0"
        :selected="multiple ? countOf(code) > 0 : singleValue === code"
        :at-limit="multiple ? countOf(code) >= maxCopies(code, decks) : false"
        variant="grid"
        :test-id-prefix="tileTestIdPrefix"
        @click="selectGridCard(code)"
      />
    </div>

    <div v-if="multiple" class="mt-3 flex min-h-[50px] items-center gap-1 overflow-x-auto">
      <span v-if="multiValue.length === 0" class="font-body text-[12px] text-mute">Nenhuma carta ainda</span>
      <GameCardTile
        v-for="(code, index) in multiValue"
        :key="`${code}-${index}`"
        :code="code"
        variant="tray"
        :test-id-prefix="tileTestIdPrefix"
        @click="removeFromTray(code)"
      />
    </div>
  </div>
</template>
