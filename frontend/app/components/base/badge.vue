<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'positive' | 'negative' | 'livre' | 'obriga' | 'team-a' | 'team-b'
}>(), {
  variant: 'positive'
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-pill px-3 py-1 font-body font-bold text-[10px] uppercase tracking-wide"
    :class="{
      'bg-primary-pale text-ink-deep': variant === 'positive' || variant === 'livre' || variant === 'team-a',
      'bg-negative/10 text-negative': variant === 'negative' || variant === 'obriga',
      'bg-canvas-soft text-body': variant === 'team-b'
    }"
  >
    <slot />
  </span>
</template>
