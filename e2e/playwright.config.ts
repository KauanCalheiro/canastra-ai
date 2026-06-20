import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests',
  reporter: [['list', { printSteps: true }]],
  use: {
    baseURL: 'http://localhost:3000'
  },
  webServer: [
    {
      command: 'php artisan serve --port=8000',
      cwd: '../backend',
      url: 'http://localhost:8000/up',
      reuseExistingServer: !process.env.CI,
      timeout: 60000
    },
    {
      command: 'pnpm dev',
      cwd: '../frontend',
      url: 'http://localhost:3000/games/new',
      reuseExistingServer: !process.env.CI,
      timeout: 60000,
      env: { NUXT_BACKEND_URL: 'http://localhost:8000' }
    }
  ]
})
