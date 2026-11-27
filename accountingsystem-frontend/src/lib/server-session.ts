import { cache } from 'react'

import { cookies } from 'next/headers'

import { parseSessionWindow } from '@/features/auth/model/session-status'
import { authMeUserSchema, meResponseSchema } from '@/features/auth/model/auth.schema'
import { LARAVEL_SESSION_COOKIE_NAME } from '@/lib/server-auth'
import { createLaravelServerHeaders } from '@/lib/server-laravel-session'

const BASE = process.env.LARAVEL_API_URL ?? process.env.NEXT_PUBLIC_LARAVEL_API_URL

/**
 * Server-only helper that resolves the authenticated user from the Laravel
 * session cookie.
 *
 * Protected layouts reuse this so server redirects can happen before React
 * renders the page shell.
 */
export const getServerSession = cache(async () => {
  if (!BASE) throw new Error('Authentication service is not configured.')

  const cookieStore = await cookies()
  const sessionCookie = cookieStore.get(LARAVEL_SESSION_COOKIE_NAME)?.value

  if (!sessionCookie) return null

  const headers = await createLaravelServerHeaders()

  const res = await fetch(`${BASE}/api/me`, {
    method: 'GET',
    headers,
    cache: 'no-store',
    signal: AbortSignal.timeout(15_000)
  })

  if (res.status === 401) return null
  if (!res.ok) throw new Error('Unable to verify the session. Please try again shortly.')

  const payload = await res.json().catch(() => null)

  // The endpoint has existed in both wrapped and unwrapped shapes across
  // backend revisions, so we accept the normalized form first and then fall
  // back to the direct user object for older responses.
  const sessionWindow = parseSessionWindow(
    res.headers.get('X-Session-Expires-At'),
    res.headers.get('X-Session-Warning-Seconds')
  )

  const parsed = meResponseSchema.safeParse(payload)

  if (parsed.success) return parsed.data.user ? { user: parsed.data.user, sessionWindow } : null

  // Fallback: some API versions return the user object directly (unwrapped).
  const direct = authMeUserSchema.safeParse(payload)

  if (direct.success) return { user: direct.data, sessionWindow }

  throw new Error('The authentication service returned an invalid response.')
})

/**
 * Public entry point used by layouts and server pages that only need the
 * resolved user object.
 */
export async function getServerAuthenticatedUser() {
  return (await getServerSession())?.user ?? null
}
