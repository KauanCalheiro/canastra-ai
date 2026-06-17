<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: number
  step?: number
}>(), {
  step: 1
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

function decrement() {
  emit('update:modelValue', props.modelValue - props.step)
}

function increment() {
  emit('update:modelValue', props.modelValue + props.step)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink bg-white font-body font-bold text-[18px] text-ink"
      @click="decrement"
    >
      −
    </button>
    <span class="rounded-md border border-ink px-4 py-3 text-center font-body font-bold text-[18px] text-ink">
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="h-12 w-12 rounded-md border border-ink bg-white font-body font-bold text-[18px] text-ink"
      @click="increment"
    >
      +
    </button>
  </div>
</template>
