<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: number
  step?: number
  min?: number
  testId?: string
}>(), {
  step: 1,
  min: -Infinity
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

function decrement() {
  const next = props.modelValue - props.step
  if (next < props.min) return
  emit('update:modelValue', next)
}

function increment() {
  emit('update:modelValue', props.modelValue + props.step)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
      :data-testid="testId ? `${testId}-decrement` : undefined"
      @click="decrement"
    >
      −
    </button>
    <span
      class="rounded-md border border-ink/15 px-4 py-3 text-center font-body font-bold text-[18px] text-ink"
      :data-testid="testId ? `${testId}-value` : undefined"
    >
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink/15 bg-white font-body font-bold text-[18px] text-ink"
      :data-testid="testId ? `${testId}-increment` : undefined"
      @click="increment"
    >
      +
    </button>
  </div>
</template>
