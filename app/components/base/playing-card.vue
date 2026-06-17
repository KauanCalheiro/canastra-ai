<script setup lang="ts">
const props = withDefaults(defineProps<{
  rank?: string
  suit?: 'hearts' | 'diamonds' | 'clubs' | 'spades'
  faceDown?: boolean
  isJoker?: boolean
}>(), {
  rank: 'A',
  suit: 'spades',
  faceDown: false,
  isJoker: false
})

const suitSymbol = {
  hearts: '♥',
  diamonds: '♦',
  clubs: '♣',
  spades: '♠'
}

const isRedSuit = props.suit === 'hearts' || props.suit === 'diamonds'
</script>

<template>
  <div
    class="flex h-[60px] w-[42px] flex-col items-center justify-center rounded-card-face border border-ink font-body font-bold"
    :class="faceDown ? 'bg-ink' : 'bg-white'"
  >
    <template v-if="!faceDown">
      <span v-if="isJoker" class="text-[18px] text-primary">W</span>
      <template v-else>
        <span class="text-[14px]" :class="isRedSuit ? 'text-card-red' : 'text-card-black'">{{ rank }}</span>
        <span class="text-[16px]" :class="isRedSuit ? 'text-card-red' : 'text-card-black'">{{ suitSymbol[suit] }}</span>
      </template>
    </template>
  </div>
</template>
