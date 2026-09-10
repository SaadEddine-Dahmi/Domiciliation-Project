import { dashboardPathForRole } from '~/utils/authRoutes'
import { useAuthStore } from '~/stores/auth'

export default defineNuxtRouteMiddleware(async () => {
  if (!import.meta.client) return

  const auth = useAuthStore()
  await auth.restoreSession()

  if (auth.isAuthenticated) {
    return navigateTo(dashboardPathForRole(auth.user?.role), { replace: true })
  }
})
