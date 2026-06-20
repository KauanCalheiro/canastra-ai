<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'outline'
}>(), {
  variant: 'primary'
})
</script>

<template>
  <button
    class="inline-flex items-center justify-center rounded-pill px-6 py-3 font-body font-bold text-[16px] transition-colors"
    :class="{
      'bg-primary text-ink hover:bg-primary-active': variant === 'primary',
      'bg-white text-ink border border-ink/15': variant === 'secondary',
      'bg-transparent text-ink border border-ink': variant === 'outline'
    }"
  >
    <slot />
  </button>
</template>
