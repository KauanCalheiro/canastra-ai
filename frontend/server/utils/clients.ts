export function canastraClient() {
  const { backendUrl } = useRuntimeConfig()

  return $fetch.create({
    baseURL: `${backendUrl}/api`
  })
}
