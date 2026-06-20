import { expect, test } from '@playwright/test'

test('cria uma partida e navega para a tela de mão inicial', async ({ page }) => {
  await test.step('acessa a página de nova partida', async () => {
    await page.goto('/games/new')
  })

  await test.step('preenche o nome dos jogadores', async () => {
    const nameInputs = page.getByTestId('player-name-input')
    await nameInputs.nth(0).fill('Ana')
    await nameInputs.nth(1).fill('Bruno')
  })

  await test.step('seleciona a pontuação alvo', async () => {
    await page.getByTestId('target-score-option-3').click()
  })

  await test.step('confirma a criação da partida', async () => {
    await page.getByTestId('submit-new-game').click()
  })

  await test.step('aguarda navegação para a tela de mão inicial', async () => {
    await page.waitForURL(/\/games\/[\w-]+\/initial-hand$/)
    await expect(page.getByTestId('initial-hand-title')).toBeVisible()
  })
})
