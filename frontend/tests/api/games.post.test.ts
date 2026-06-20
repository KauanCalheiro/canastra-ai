import { afterAll, describe, expect, it } from 'vitest'
import { $fetch, setup } from '@nuxt/test-utils/e2e'
import Database from 'better-sqlite3'
import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const testDbDir = mkdtempSync(join(tmpdir(), 'canastra-test-'))
const testDbPath = join(testDbDir, 'canastra.sqlite')

await setup({
  rootDir: process.cwd(),
  env: { DB_PATH: testDbPath }
})

afterAll(() => {
  rmSync(testDbDir, { recursive: true, force: true })
})

describe('POST /api/games', () => {
  it('creates a game with 2 players and persists it', async () => {
    const result = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: { decks: 2, targetScore: 3000, players: ['Ana', 'Bruno'] }
    })

    expect(result.id).toBeTypeOf('string')

    const db = new Database(testDbPath, { readonly: true })
    const game = db.prepare('SELECT * FROM games WHERE id = ?').get(result.id) as Record<string, unknown>
    const players = db.prepare('SELECT * FROM players WHERE game_id = ? ORDER BY seat_index').all(result.id) as Record<string, unknown>[]
    db.close()

    expect(game).toMatchObject({ decks: 2, target_score: 3000 })
    expect(players).toHaveLength(2)
    expect(players[0]).toMatchObject({ seat_index: 0, name: 'Ana' })
    expect(players[1]).toMatchObject({ seat_index: 1, name: 'Bruno' })
  })

  it('creates a game with 4 players', async () => {
    const result = await $fetch<{ id: string }>('/api/games', {
      method: 'POST',
      body: { decks: 1, targetScore: 1500, players: ['Ana', 'Bruno', 'Carla', 'Diego'] }
    })

    const db = new Database(testDbPath, { readonly: true })
    const players = db.prepare('SELECT * FROM players WHERE game_id = ? ORDER BY seat_index').all(result.id) as Record<string, unknown>[]
    db.close()

    expect(players).toHaveLength(4)
  })

  it('rejects an invalid number of decks', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 5, targetScore: 3000, players: ['Ana', 'Bruno'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })

  it('rejects a target score below 100', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 2, targetScore: 50, players: ['Ana', 'Bruno'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })

  it('rejects a player count outside 2 or 4', async () => {
    await expect(
      $fetch('/api/games', {
        method: 'POST',
        body: { decks: 2, targetScore: 3000, players: ['Ana', 'Bruno', 'Carla'] }
      })
    ).rejects.toMatchObject({ statusCode: 400 })
  })
})
