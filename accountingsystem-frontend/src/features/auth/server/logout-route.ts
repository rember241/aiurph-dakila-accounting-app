import { NextResponse } from 'next/server'

import { expireAuthCookies } from '@/lib/auth-cookie-reset'
import { originFromRequestHeaders } from '@/lib/runtime-origin'

const BASE = process.env.LARAVEL_API_URL ?? process.env.NEXT_PUBLIC_LARAVEL_API_URL

function readCookie(request: Request, name: string): string | null {
  const raw = request.headers.get('cookie') ?? ''

  const found = raw
    .split(';')
    .map(value => value.trim())
    .find(value => value.startsWith(`${name}=`))

  return found ? decodeURIComponent(found.slice(name.length + 1)) : null
}

/**
 * Origin to send the user back to after logout.
 *
 * Prefers the real browser Host (works for the LAN IP or any host the app is
 * reached by) and falls back to the configured public app URL. Deliberately
 * avoids `request.url`, whose host is unreliable inside a Next.js route handler
 * under `next start` (it can surface as `localhost`).
 */
function loginRedirectOrigin(request: Request): string {
  const headerHost = request.headers.get('x-forwarded-host') ?? request.headers.get('host')

  if (headerHost) {
    const proto = request.headers.get('x-forwarded-proto') ?? 'http'

    return `${proto}://${headerHost}`
  }

  return process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3001'
}

async function invalidateBackendSession(request: Request) {
  if (!BASE) {
    return
  }

  const cookie = request.headers.get('cookie')

  if (!cookie) {
    return
  }

  const xsrfToken = readCookie(request, 'XSRF-TOKEN')

  const headers = new Headers({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    Referer: originFromRequestHeaders(request.headers),
    Cookie: cookie
  })

  if (xsrfToken) {
    headers.set('X-XSRF-TOKEN', xsrfToken)
  }

  try {
    // NOTE: the backend logout route is `api/logout` (see backend
    // routes/api.php → IdentityAccess). Omitting the `/api` prefix here hit a
    // non-existent route, so the Laravel session was never invalidated and
    // survived logout + server restarts.
    await fetch(`${BASE}/api/logout`, {
      method: 'POST',
      headers,
      cache: 'no-store',
      signal: AbortSignal.timeout(3_000)
    })
  } catch {
    // Best effort. Browser-facing cookie deletion below is the critical part.
  }
}

export async function handleLogoutRoute(request: Request) {
  await invalidateBackendSession(request)

  // Build the redirect target from the request's Host/X-Forwarded-Host header,
  // NOT from `request.url`. In a Next.js route handler under `next start`,
  // `request.url`'s host can resolve to the server's bound address
  // (`localhost:3001`) instead of the address the browser actually used
  // (e.g. `192.168.1.150:3001`), which would bounce the user to localhost
  // after logout. Prefer the real Host header; fall back to the configured
  // app URL — never to a hardcoded localhost.
  const response = NextResponse.redirect(new URL('/login', loginRedirectOrigin(request)))

  expireAuthCookies(response)
  response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
  response.headers.set('Pragma', 'no-cache')
  response.headers.set('Expires', '0')

  return response
}
