import { type NextResponse } from 'next/server'

import { CURRENT_TENANT_COOKIE } from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { cookiesAreSecure } from '@/lib/cookie-security'
import { LARAVEL_SESSION_COOKIE_NAME } from '@/lib/server-auth'

function expireCookie(response: NextResponse, name: string, httpOnly = false) {
  response.cookies.set(name, '', {
    httpOnly,
    secure: cookiesAreSecure(),
    sameSite: 'lax',
    path: '/',
    maxAge: 0
  })
}

export function expireAuthCookies(response: NextResponse) {
  expireCookie(response, LARAVEL_SESSION_COOKIE_NAME, true)
  expireCookie(response, 'laravel_session', true)
  expireCookie(response, 'XSRF-TOKEN')
  expireCookie(response, CURRENT_TENANT_COOKIE)
}
