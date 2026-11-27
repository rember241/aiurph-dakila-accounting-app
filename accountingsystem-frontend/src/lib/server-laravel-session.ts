import { cookies, headers as requestHeaders } from 'next/headers'

import {
  CURRENT_TENANT_COOKIE,
  parseCurrentTenantId
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { LARAVEL_SESSION_COOKIE_NAME } from '@/lib/server-auth'

type ServerLaravelHeaderOptions = {
  includeTenant?: boolean
}

export async function hasServerLaravelSession() {
  const cookieStore = await cookies()

  return !!cookieStore.get(LARAVEL_SESSION_COOKIE_NAME)?.value
}

export async function getServerCurrentTenantId() {
  const cookieStore = await cookies()

  return parseCurrentTenantId(cookieStore.get(CURRENT_TENANT_COOKIE)?.value)
}

export async function createLaravelServerHeaders(
  extra: HeadersInit = {},
  options: ServerLaravelHeaderOptions = {}
): Promise<Headers> {
  const headers = new Headers(extra)

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  // Required for Sanctum EnsureFrontendRequestsAreStateful to activate session middleware
  if (!headers.has('Referer')) {
    headers.set('Referer', process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3001')
  }

  const cookieHeader = (await requestHeaders()).get('cookie')

  if (cookieHeader) {
    headers.set('Cookie', cookieHeader)
  }

  if (options.includeTenant) {
    const cookieStore = await cookies()
    const tenantId = parseCurrentTenantId(cookieStore.get(CURRENT_TENANT_COOKIE)?.value)

    if (tenantId) {
      headers.set('X-Company-ID', tenantId)
      headers.set('X-Tenant-ID', tenantId)
    }
  }

  return headers
}
