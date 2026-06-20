<script setup lang="ts">
withDefaults(defineProps<{
  initial: string
  name: string
  team?: 'A' | 'B'
  active?: boolean
}>(), {
  team: 'A',
  active: true
})
</script>

<template>
  <div class="flex items-center gap-2" :class="active ? 'opacity-100' : 'opacity-50'">
    <span
      class="flex h-8 w-8 items-center justify-center rounded-full font-body font-bold text-[12px]"
      :class="team === 'A' ? 'bg-primary text-ink-deep' : 'bg-canvas-soft text-ink'"
    >
      {{ initial }}
    </span>
    <span class="font-body font-semibold text-[14px] text-ink">{{ name }}</span>
  </div>
</template>
