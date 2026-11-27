import { type NextRequest, NextResponse } from 'next/server'

import {
  CURRENT_TENANT_COOKIE,
  parseCurrentTenantId
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { clearCurrentTenantCookie } from '@/lib/current-tenant-cookie'
import { LARAVEL_SESSION_COOKIE_NAME } from '@/lib/server-auth'

/**
 * Front-door guard for page requests.
 *
 * The API routes do their own authorization, but page requests still need a
 * lightweight session check so protected layouts never boot with an anonymous
 * browser session. We also clean up the legacy current-tenant cookie here so
 * stale company context does not survive logout.
 */
export async function proxy(req: NextRequest) {
  const session = req.cookies.get(LARAVEL_SESSION_COOKIE_NAME)?.value
  const rawCurrentTenantId = req.cookies.get(CURRENT_TENANT_COOKIE)?.value
  const currentTenantId = parseCurrentTenantId(rawCurrentTenantId)
  const { pathname } = req.nextUrl

  const publicPath =
    pathname === '/login' ||
    pathname === '/logout' ||
    pathname.startsWith('/images/') ||
    pathname.startsWith('/favicon') ||
    pathname === '/site.webmanifest' ||
    pathname === '/robots.txt' ||
    pathname === '/sitemap.xml'

  if (!session && !publicPath) {
    // Keep protected routes server-driven. If we let these requests reach the
    // layout tree, every page would need to repeat the same redirect logic.
    const response = NextResponse.redirect(new URL('/login', req.url))

    clearCurrentTenantCookie(response)

    return response
  }

  // Disabled modules must also be unavailable through bookmarks and direct URLs.
  const disabledSections = ['/sales', '/customers', '/inventory', '/expenses', '/settings', '/users/companies', '/users/company-structure', '/users/user-management']

  if (disabledSections.some(section => pathname === section || pathname.startsWith(`${section}/`))) {
    return new NextResponse('This module is disabled in the accounting-only application.', { status: 404 })
  }

  const response = NextResponse.next()

  if (!session) {
    clearCurrentTenantCookie(response)
  } else if (!currentTenantId && rawCurrentTenantId) {
    clearCurrentTenantCookie(response)
  }

  // Prevent serving protected pages from browser/proxy caches after logout.
  if (!publicPath) {
    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
    response.headers.set('Pragma', 'no-cache')
    response.headers.set('Expires', '0')
  }

  return response
}

export const config = {
  // Gate pages only. The frontend proxy should stay out of API auth because the
  // route handlers already forward the Laravel session and permission checks.
  matcher: ['/((?!api|_next/static|_next/image|images/|favicon.ico|site.webmanifest|robots.txt|sitemap.xml).*)']
}
