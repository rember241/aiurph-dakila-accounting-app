import { handleLogoutRoute } from '@/features/auth/server/logout-route'

export async function GET(request: Request) {
  return handleLogoutRoute(request)
}
