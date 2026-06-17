<script setup lang="ts">
defineProps<{
  options: { label: string, value: string }[]
  modelValue: string
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
      class="h-[46px] flex-1 rounded-md border font-body font-bold text-[16px] text-ink"
      :class="option.value === modelValue ? 'border-ink bg-primary' : 'border-ink/15 bg-white'"
      @click="$emit('update:modelValue', option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
