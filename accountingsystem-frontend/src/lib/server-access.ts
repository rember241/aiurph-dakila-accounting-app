import { cache } from 'react'

import { cookies } from 'next/headers'

import {
  createEmptyCurrentTenantAccessState,
  currentTenantAccessStateSchema,
  hasAnyCurrentTenantAbility,
  hasAllCurrentTenantAbilities,
  hasCurrentTenantAbility
} from '@/features/auth/access/model/access.schema'
import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'
import { LARAVEL_SESSION_COOKIE_NAME } from '@/lib/server-auth'
import { createLaravelServerHeaders } from '@/lib/server-laravel-session'

const BASE = process.env.LARAVEL_API_URL ?? process.env.NEXT_PUBLIC_LARAVEL_API_URL

/**
 * Server-only access resolver used by layouts, pages, and route handlers.
 *
 * We cache this per request tree so multiple permission checks on the same
 * render do not fan out into repeated `/api/v1/me/access` calls.
 */
const resolveServerCurrentTenantAccess = cache(async (): Promise<CurrentTenantAccessState> => {
  if (!BASE) throw new Error('Access service is not configured.')

  const cookieStore = await cookies()
  const sessionCookie = cookieStore.get(LARAVEL_SESSION_COOKIE_NAME)?.value

  if (!sessionCookie) return createEmptyCurrentTenantAccessState()

  const headers = await createLaravelServerHeaders({}, { includeTenant: true })

  const res = await fetch(`${BASE}/api/v1/me/access`, {
    method: 'GET',
    headers,
    cache: 'no-store',
    signal: AbortSignal.timeout(15_000)
  })

  if (res.status === 401) return createEmptyCurrentTenantAccessState()
  if (!res.ok) throw new Error('Unable to load company access. Please try again shortly.')

  const payload = await res.json().catch(() => null)
  const parsed = currentTenantAccessStateSchema.safeParse(payload)

  if (!parsed.success) throw new Error('The access service returned an invalid response.')

  return parsed.data
})

/**
 * Returns the current company access snapshot in the normalized frontend shape.
 */
export async function getServerCurrentTenantAccess(): Promise<CurrentTenantAccessState> {
  return resolveServerCurrentTenantAccess()
}

/**
 * Convenience wrapper for single-ability checks in server components.
 */
export async function serverCan(ability: string): Promise<boolean> {
  const access = await resolveServerCurrentTenantAccess()

  return hasCurrentTenantAbility(access.data, ability)
}

/**
 * Returns true when any one of the requested abilities is available.
 */
export async function serverCanAny(abilities: readonly string[]): Promise<boolean> {
  const access = await resolveServerCurrentTenantAccess()

  return hasAnyCurrentTenantAbility(access.data, abilities)
}

/**
 * Returns true only when the current company role grants the full ability set.
 */
export async function serverCanAll(abilities: readonly string[]): Promise<boolean> {
  const access = await resolveServerCurrentTenantAccess()

  return hasAllCurrentTenantAbilities(access.data, abilities)
}
