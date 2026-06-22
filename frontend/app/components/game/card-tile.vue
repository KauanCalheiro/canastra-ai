<script setup lang="ts">
import { computed } from 'vue'
import { parseCard } from '~/utils/cards'

const props = withDefaults(defineProps<{
  code: string
  count?: number
  selected?: boolean
  atLimit?: boolean
  variant?: 'grid' | 'tray'
  testIdPrefix?: string
}>(), {
  count: 0,
  selected: false,
  atLimit: false,
  variant: 'grid',
  testIdPrefix: 'hand-card'
})

defineEmits<{ click: [] }>()

const card = computed(() => parseCard(props.code))
</script>

<template>
  <button
    type="button"
    class="flex flex-col items-center justify-center gap-0.5 rounded-md border font-body font-bold"
    :class="[
      variant === 'grid' ? 'relative h-[62px]' : 'h-[48px] w-[34px] shrink-0',
      selected ? 'border-ink bg-primary' : 'border-ink/15 bg-white',
      atLimit ? 'cursor-default opacity-30' : 'cursor-pointer'
    ]"
    :disabled="atLimit && variant === 'grid'"
    :data-testid="`${testIdPrefix}-${variant}-${code}`"
    @click="$emit('click')"
  >
    <span class="text-[15px] leading-none" :class="card.isRed ? 'text-card-red' : 'text-card-black'">{{ card.label }}</span>
    <span class="text-[15px] leading-none" :class="card.isJoker ? 'text-ink-deep' : (card.isRed ? 'text-card-red' : 'text-card-black')">{{ card.suitSymbol }}</span>
    <div
      v-if="variant === 'grid' && count > 0"
      class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-pill bg-ink px-1"
    >
      <span class="text-[9px] font-extrabold text-primary">{{ count }}</span>
    </div>
  </button>
</template>
