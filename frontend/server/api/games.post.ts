export default defineEventHandler(async (event) => {
  const body = await readBody(event)

  try {
    return await canastraClient()('/games', { method: 'POST', body })
  } catch (error) {
    if (error instanceof Error && 'statusCode' in error) {
      const fetchError = error as Error & { statusCode: number, data?: unknown }
      throw createError({ statusCode: fetchError.statusCode, data: fetchError.data })
    }

    throw error
  }
})
