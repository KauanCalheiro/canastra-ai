<script setup lang="ts">
defineProps<{
  modelValue: string
  placeholder?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <input
    :value="modelValue"
    :placeholder="placeholder"
    class="rounded-md border border-ink bg-white px-4 py-3 font-body text-[16px] text-ink"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  >
</template>
