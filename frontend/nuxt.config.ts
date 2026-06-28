// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  runtimeConfig: {
    backendUrl: 'http://localhost:8000'
  },
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/google-fonts', '@nuxt/icon'],
  googleFonts: {
    families: {
      Manrope: [900],
      Inter: [400, 600, 700]
    },
    display: 'swap'
  }
})
