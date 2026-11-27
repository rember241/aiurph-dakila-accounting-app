import {
  authMeUserSchema,
  loginInputSchema,
  loginResponseSchema,
  logoutResponseSchema,
  meResponseSchema
} from '@/features/auth/model/auth.schema'

import type { LoginResponse, LogoutResponse, MeResponse } from '@/features/auth/model/auth.schema'
import { parseSessionWindow } from '@/features/auth/model/session-status'
import { bootstrapLaravelCsrf, laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

/**
 * Starts a browser session against Laravel and returns the normalized user
 * envelope the rest of the frontend expects.
 */
export async function login(input: unknown): Promise<LoginResponse> {
  const payload = loginInputSchema.parse(input)

  await bootstrapLaravelCsrf()

  const res = await laravelBrowserClient.post<LoginResponse>('/api/login', payload)

  return loginResponseSchema.parse(res.data)
}

/**
 * Ends the current browser session.
 *
 * Laravel installations are not fully consistent here, so the frontend accepts
 * either a normal message payload or an empty 204 response.
 */
export async function logout(): Promise<LogoutResponse> {
  const res = await laravelBrowserClient.post('/api/logout')
  const parsed = logoutResponseSchema.safeParse(res.data)

  // Accept any 2xx response — some Laravel setups return 204 with no body.
  return parsed.success ? parsed.data : { message: 'Logged out' }
}

/**
 * Loads the authenticated user and the minimal company/onboarding metadata
 * needed by protected layouts.
 */
export async function me(): Promise<MeResponse> {
  const res = await laravelBrowserClient.get('/api/me')

  // Laravel's /api/me returns the user object at the root level
  // ({ id, name, email, company, onboarding }). Older API versions wrap it as
  // { user: {...} }. Accept either shape, normalize to the client-facing
  // { user } envelope so useMeQuery consumers keep working.
  const wrapped = meResponseSchema.safeParse(res.data)

  if (wrapped.success) return wrapped.data

  const direct = authMeUserSchema.safeParse(res.data)

  if (direct.success) return { user: direct.data }

  // Surface the original (wrapped-shape) parse error for diagnostics when
  // neither shape matches — this is the most informative failure message.
  return meResponseSchema.parse(res.data)
}

export async function extendSession() {
  const response = await laravelBrowserClient.post('/api/session/extend')

  const sessionWindow = parseSessionWindow(
    response.headers['x-session-expires-at'],
    response.headers['x-session-warning-seconds']
  )

  if (!sessionWindow) throw new Error('The server did not confirm the extended session.')
}
