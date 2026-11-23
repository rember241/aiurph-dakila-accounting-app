import type { NextResponse } from 'next/server'

import {
  CURRENT_TENANT_COOKIE,
  CURRENT_TENANT_COOKIE_MAX_AGE
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { cookiesAreSecure } from '@/lib/cookie-security'

function currentTenantCookieOptions() {
  return {
    httpOnly: true,
    secure: cookiesAreSecure(),
    sameSite: 'lax' as const,
    path: '/',
    maxAge: CURRENT_TENANT_COOKIE_MAX_AGE
  }
}

export function setCurrentTenantCookie(response: NextResponse, tenantId: string) {
  response.cookies.set(CURRENT_TENANT_COOKIE, tenantId, currentTenantCookieOptions())
}

export function clearCurrentTenantCookie(response: NextResponse) {
  response.cookies.set(CURRENT_TENANT_COOKIE, '', {
    ...currentTenantCookieOptions(),
    maxAge: 0
  })
}
