export default defineEventHandler(async (event) => {
  const id = getRouterParam(event, 'id')

  try {
    return await canastraClient()(`/games/${id}`)
  } catch (error) {
    if (error instanceof Error && 'statusCode' in error) {
      const fetchError = error as Error & { statusCode: number, data?: unknown }
      throw createError({ statusCode: fetchError.statusCode, data: fetchError.data })
    }

    throw error
  }
})
