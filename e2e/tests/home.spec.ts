import { expect, test } from '@playwright/test'

test('acessa a home e navega para a criação de partida', async ({ page }) => {
  await test.step('acessa a home', async () => {
    await page.goto('/', { waitUntil: 'networkidle' })
    await expect(page.getByTestId('home-title')).toBeVisible()
    await expect(page.getByTestId('home-tagline')).toBeVisible()
  })

  await test.step('clica em nova partida e navega para a tela de criação', async () => {
    await page.getByTestId('home-new-game').click()
    await page.waitForURL(/\/games\/new$/)
  })
})
