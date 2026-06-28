<script setup lang="ts">
</script>

<template>
  <div class="flex min-h-screen flex-col bg-canvas-soft">
    <header class="px-6 py-5">
      <h1 class="font-display font-black text-[22px] text-ink" data-testid="home-title">canastra ai</h1>
    </header>

    <div class="flex flex-1 flex-col items-center justify-center px-6 text-center">
      <p class="font-body text-[16px] text-body" data-testid="home-tagline">
        Registre partidas de canastra e acompanhe a pontuação com a ajuda da IA.
      </p>
    </div>

    <footer class="px-6 py-4">
      <BaseButton
        variant="primary"
        class="w-full justify-center"
        data-testid="home-new-game"
        @click="navigateTo('/games/new')"
      >
        Nova partida
        <Icon name="mdi:arrow-right" class="ml-2 text-[20px]" />
      </BaseButton>
    </footer>
  </div>
</template>
