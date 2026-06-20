import Database from 'better-sqlite3'
import { mkdirSync } from 'node:fs'
import { dirname } from 'node:path'

let db: Database.Database | null = null

export function getDb(): Database.Database {
  if (db) return db

  const path = process.env.DB_PATH ?? '.data/canastra.sqlite'
  mkdirSync(dirname(path), { recursive: true })

  db = new Database(path)
  db.exec(`
    CREATE TABLE IF NOT EXISTS games (
      id TEXT PRIMARY KEY,
      decks INTEGER NOT NULL,
      target_score INTEGER NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS players (
      id TEXT PRIMARY KEY,
      game_id TEXT NOT NULL REFERENCES games(id),
      seat_index INTEGER NOT NULL,
      name TEXT NOT NULL DEFAULT ''
    );
  `)

  return db
}
