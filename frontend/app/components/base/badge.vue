<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'positive' | 'negative' | 'livre' | 'obriga'
}>(), {
  variant: 'positive'
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-pill px-3 py-1 font-body font-bold text-[10px] uppercase tracking-wide"
    :class="{
      'bg-primary-pale text-ink-deep': variant === 'positive' || variant === 'livre',
      'bg-negative/10 text-negative': variant === 'negative' || variant === 'obriga'
    }"
  >
    <slot />
  </span>
</template>
