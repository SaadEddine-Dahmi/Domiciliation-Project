import type { Role } from '~/stores/auth'

export function dashboardPathForRole(role?: Role | null): string {
  return role === 'client' ? '/client/dashboard' : '/admin/dashboard'
}
