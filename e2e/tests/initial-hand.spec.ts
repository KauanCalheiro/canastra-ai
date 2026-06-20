import { expect, test, type Page } from '@playwright/test'

async function createGameAndReachHandScreen(page: Page, decks: '1' | '2' | '3' = '2') {
  await page.goto('/games/new', { waitUntil: 'networkidle' })
  const nameInputs = page.getByTestId('player-name-input')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')
  await page.getByTestId(`decks-option-${decks}`).click()
  await page.getByTestId('submit-new-game').click()
  await page.waitForURL(/\/games\/[\w-]+\/initial-hand$/)
  await expect(page.getByTestId('initial-hand-title')).toBeVisible()
}

test('registra as 13 cartas da mão inicial, incluindo duplicatas', async ({ page }) => {
  await test.step('cria uma partida com 2 baralhos e chega na tela de registrar mão', async () => {
    await createGameAndReachHandScreen(page, '2')
  })

  await test.step('seleciona cartas de naipes diferentes', async () => {
    await page.getByTestId('hand-suit-tab-H').click()
    await page.getByTestId('hand-card-grid-AH').click()
    await page.getByTestId('hand-suit-tab-S').click()
    await page.getByTestId('hand-card-grid-2S').click()
    await expect(page.getByTestId('hand-count')).toHaveText('2 / 13 · toque p/ remover')
  })

  await test.step('toca duas vezes na mesma carta para repetir (duplicata permitida com 2 baralhos)', async () => {
    await page.getByTestId('hand-card-grid-2S').click()
    await expect(page.getByTestId('hand-count')).toHaveText('3 / 13 · toque p/ remover')
  })

  await test.step('remove uma carta pela bandeja', async () => {
    await page.getByTestId('hand-card-tray-2S').first().click()
    await expect(page.getByTestId('hand-count')).toHaveText('2 / 13 · toque p/ remover')
    await expect(page.getByTestId('confirm-hand')).toBeDisabled()
  })

  await test.step('completa as 13 cartas', async () => {
    await page.getByTestId('hand-card-grid-2S').click()
    await page.getByTestId('hand-card-grid-3S').click()
    await page.getByTestId('hand-card-grid-4S').click()
    await page.getByTestId('hand-card-grid-5S').click()
    await page.getByTestId('hand-card-grid-6S').click()
    await page.getByTestId('hand-card-grid-7S').click()
    await page.getByTestId('hand-card-grid-8S').click()
    await page.getByTestId('hand-card-grid-9S').click()
    await page.getByTestId('hand-card-grid-TS').click()
    await page.getByTestId('hand-card-grid-JS').click()
    await page.getByTestId('hand-card-grid-QS').click()
    await expect(page.getByTestId('hand-count')).toHaveText('13 / 13 · toque p/ remover')
  })

  await test.step('confirma a mão e vê a tela de sucesso', async () => {
    await page.getByTestId('confirm-hand').click()
    await expect(page.getByTestId('initial-hand-success')).toBeVisible()
  })
})
