<script setup lang="ts">
defineProps<{
  index: number
  name: string
  team: 'A' | 'B'
  canMoveUp: boolean
  canMoveDown: boolean
}>()

defineEmits<{
  'update:name': [value: string]
  moveUp: []
  moveDown: []
}>()
</script>

<template>
  <div class="flex items-center gap-3">
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-pill font-body font-bold text-[16px]"
      :class="team === 'A' ? 'bg-primary text-ink-deep' : 'bg-ink text-white'"
    >
      {{ name.trim().charAt(0).toUpperCase() }}
    </div>
    <BaseInput
      :model-value="name"
      placeholder="Nome do jogador"
      class="flex-1"
      data-testid="player-name-input"
      @update:model-value="$emit('update:name', $event)"
    />
    <BaseBadge
      :variant="team === 'A' ? 'team-a' : 'team-b'"
      :data-testid="`player-team-badge-${index}`"
    >
      Dupla {{ team }}
    </BaseBadge>
    <div class="flex flex-col gap-1">
      <button
        type="button"
        class="text-[12px] text-ink disabled:opacity-20"
        :disabled="!canMoveUp"
        :data-testid="`player-move-up-${index}`"
        @click="$emit('moveUp')"
      >
        ▲
      </button>
      <button
        type="button"
        class="text-[12px] text-ink disabled:opacity-20"
        :disabled="!canMoveDown"
        :data-testid="`player-move-down-${index}`"
        @click="$emit('moveDown')"
      >
        ▼
      </button>
    </div>
  </div>
</template>
