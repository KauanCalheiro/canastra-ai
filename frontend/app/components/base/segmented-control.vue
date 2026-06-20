<script setup lang="ts">
defineProps<{
  options: { label: string, value: string }[]
  modelValue: string
  testId?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="flex gap-2">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      class="h-[46px] flex-1 rounded-md font-body font-bold text-[16px] text-ink"
      :class="option.value === modelValue ? 'border border-transparent bg-primary' : 'border border-ink/15 bg-white'"
      :data-testid="testId ? `${testId}-option-${option.value}` : undefined"
      @click="$emit('update:modelValue', option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
