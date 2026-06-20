import { v4 as uuidv4 } from 'uuid'
import { getDb } from '../utils/db'

interface CreateGameBody {
  decks: number
  targetScore: number
  players: string[]
}

export default defineEventHandler(async (event) => {
  const body = await readBody<CreateGameBody>(event)

  if (![1, 2, 3].includes(body.decks)) {
    throw createError({ statusCode: 400, statusMessage: 'Número de baralhos deve ser 1, 2 ou 3.' })
  }
  if (!Number.isInteger(body.targetScore) || body.targetScore < 100) {
    throw createError({ statusCode: 400, statusMessage: 'Pontuação para vencer deve ser um inteiro maior ou igual a 100.' })
  }
  if (!Array.isArray(body.players) || (body.players.length !== 2 && body.players.length !== 4)) {
    throw createError({ statusCode: 400, statusMessage: 'É necessário informar 2 ou 4 jogadores.' })
  }

  const db = getDb()
  const gameId = uuidv4()

  const insert = db.transaction(() => {
    db.prepare(
      'INSERT INTO games (id, decks, target_score) VALUES (?, ?, ?)'
    ).run(gameId, body.decks, body.targetScore)

    const insertPlayer = db.prepare(
      'INSERT INTO players (id, game_id, seat_index, name) VALUES (?, ?, ?, ?)'
    )
    body.players.forEach((name, seatIndex) => {
      insertPlayer.run(uuidv4(), gameId, seatIndex, name)
    })
  })

  insert()

  return { id: gameId }
})
