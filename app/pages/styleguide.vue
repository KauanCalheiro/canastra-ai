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
  </div>
</template>
