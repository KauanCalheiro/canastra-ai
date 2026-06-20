import { expect, test } from '@playwright/test'

test('cria uma partida e navega para a tela de mão inicial', async ({ page }) => {
  await page.goto('/games/new')

  const nameInputs = page.getByPlaceholder('Nome do jogador')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')

  await page.getByRole('button', { name: '3', exact: true }).click()
  await page.getByRole('button', { name: 'Registrar minha mão →' }).click()

  await page.waitForURL(/\/games\/[\w-]+\/initial-hand$/)
  await expect(page.getByText('Partida')).toBeVisible()
})
