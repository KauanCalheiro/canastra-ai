import { expect, test, type Page } from '@playwright/test'

async function createGameWithRegisteredHand(page: Page): Promise<string> {
  await page.goto('/games/new', { waitUntil: 'networkidle' })
  const nameInputs = page.getByTestId('player-name-input')
  await nameInputs.nth(0).fill('Ana')
  await nameInputs.nth(1).fill('Bruno')
  await page.getByTestId('decks-option-2').click()
  await page.getByTestId('submit-new-game').click()
  await page.waitForURL(/\/games\/([\w-]+)\/initial-hand$/)

  const cards = ['AH', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', 'TS', 'JS', 'QS', 'KS']
  for (const code of cards) {
    const suit = code.slice(-1)
    await page.getByTestId(`hand-suit-tab-${suit}`).click()
    await page.getByTestId(`hand-card-grid-${code}`).click()
  }
  await page.getByTestId('confirm-hand').click()
  await expect(page.getByTestId('initial-hand-success')).toBeVisible()

  const match = page.url().match(/\/games\/([\w-]+)\/initial-hand$/)
  if (!match) throw new Error('game id not found in URL')

  return match[1]
}

test('registra uma jogada completa e passa a vez', async ({ page }) => {
  let gameId = ''

  await test.step('cria a partida e registra a mão inicial', async () => {
    gameId = await createGameWithRegisteredHand(page)
  })

  await test.step('acessa a tela de registrar jogada', async () => {
    await page.goto(`/games/${gameId}/play`, { waitUntil: 'networkidle' })
    await expect(page.getByTestId('play-turn-name')).toHaveText('Ana')
  })

  await test.step('compra do monte e informa a carta comprada', async () => {
    await page.getByTestId('play-draw-monte').click()
    await page.getByTestId('drawn-code-suit-tab-D').click()
    await page.getByTestId('drawn-code-card-grid-AD').click()
  })

  await test.step('não baixa nada na mesa', async () => {
    await page.getByTestId('play-lower-no').click()
  })

  await test.step('descarta a carta comprada', async () => {
    await page.getByTestId('discarded-code-suit-tab-D').click()
    await page.getByTestId('discarded-code-card-grid-AD').click()
  })

  await test.step('registra a jogada e passa a vez', async () => {
    await page.getByTestId('register-play').click()
    await expect(page.getByTestId('play-turn-name')).toHaveText('Bruno')
  })
})
