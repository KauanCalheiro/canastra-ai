<script setup lang="ts">
const route = useRoute()
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas-soft px-6 text-center">
    <p class="font-body text-[16px] text-ink" data-testid="initial-hand-title">
      Partida {{ route.params.id }} criada. Registrar mão em breve.
    </p>
  </div>
</template>
