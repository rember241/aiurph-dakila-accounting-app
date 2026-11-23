import { handleNavSearchRoute } from '@/features/navigation/server/nav-search-route'

export function GET(request: Request): Response {
  return handleNavSearchRoute(request)
}
