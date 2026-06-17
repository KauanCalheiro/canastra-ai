<script setup lang="ts">
import { ref } from 'vue'

const inputValue = ref('')
const stepperValue = ref(2)
</script>

<template>
  <div class="min-h-screen bg-canvas-soft px-6 py-10">
    <h1 class="mb-8 font-display font-black text-[32px] text-ink">
      Styleguide
    </h1>

    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Buttons</h2>
      <p class="mb-4 font-body text-[14px] text-body">Primary, secondary and outline variants.</p>
      <BaseCard class="flex gap-4">
        <BaseButton variant="primary">Primary</BaseButton>
        <BaseButton variant="secondary">Secondary</BaseButton>
        <BaseButton variant="outline">Outline</BaseButton>
      </BaseCard>
    </section>

    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Card</h2>
      <p class="mb-4 font-body text-[14px] text-body">White and sage surfaces.</p>
      <div class="flex gap-4">
        <BaseCard surface="white">
          <p class="font-body text-[14px] text-ink">White card</p>
        </BaseCard>
        <BaseCard surface="sage">
          <p class="font-body text-[14px] text-ink">Sage card</p>
        </BaseCard>
      </div>
    </section>

    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Badge</h2>
      <p class="mb-4 font-body text-[14px] text-body">Positive, negative, livre and obriga states.</p>
      <BaseCard class="flex gap-3">
        <BaseBadge variant="positive">Positive</BaseBadge>
        <BaseBadge variant="negative">Negative</BaseBadge>
        <BaseBadge variant="livre">Livre</BaseBadge>
        <BaseBadge variant="obriga">Obriga</BaseBadge>
      </BaseCard>
    </section>

    <section class="mb-8">
      <h2 class="mb-1 font-body font-semibold text-[24px] text-ink">Input &amp; Stepper</h2>
      <p class="mb-4 font-body text-[14px] text-body">Text input and numeric stepper, both interactive.</p>
      <BaseCard class="flex items-center gap-6">
        <BaseInput v-model="inputValue" placeholder="Nome do jogador" />
        <BaseStepper v-model="stepperValue" :step="100" />
      </BaseCard>
    </section>
  </div>
</template>
