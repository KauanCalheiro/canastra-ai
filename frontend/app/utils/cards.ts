export type Suit = 'S' | 'H' | 'C' | 'D'

export const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K'] as const

export const SUITS: { key: Suit, symbol: string, red: boolean }[] = [
  { key: 'H', symbol: '♥', red: true },
  { key: 'D', symbol: '♦', red: true },
  { key: 'C', symbol: '♣', red: false },
  { key: 'S', symbol: '♠', red: false }
]

export interface ParsedCard {
  code: string
  rank: string
  suit: Suit | null
  isJoker: boolean
  label: string
  suitSymbol: string
  isRed: boolean
}

export function parseCard(code: string): ParsedCard {
  if (code === 'W') {
    return { code, rank: 'W', suit: null, isJoker: true, label: 'W', suitSymbol: '★', isRed: false }
  }

  const suit = code.slice(-1) as Suit
  const rawRank = code.slice(0, -1)
  const label = rawRank === 'T' ? '10' : rawRank
  const suitInfo = SUITS.find((s) => s.key === suit)

  return {
    code,
    rank: rawRank,
    suit,
    isJoker: false,
    label,
    suitSymbol: suitInfo?.symbol ?? '',
    isRed: suitInfo?.red ?? false
  }
}

export function maxCopies(code: string, decks: number): number {
  return code === 'W' ? decks * 2 : decks
}
