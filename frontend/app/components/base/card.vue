<script setup lang="ts">
withDefaults(defineProps<{
  surface?: 'white' | 'sage'
}>(), {
  surface: 'white'
})
</script>

<template>
  <div
    class="rounded-card p-6"
    :class="surface === 'sage' ? 'bg-canvas-soft' : 'bg-white'"
  >
    <slot />
  </div>
</template>
