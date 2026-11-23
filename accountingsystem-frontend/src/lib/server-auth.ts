/**
 * This module provides utility functions for interacting with the Laravel backend in a Next.js application.
 *
 * @module server-auth
 */

import type { NextResponse } from 'next/server'

export const LARAVEL_SESSION_COOKIE_NAME = process.env.LARAVEL_SESSION_COOKIE_NAME!

import {
  CURRENT_TENANT_COOKIE,
  parseCurrentTenantId
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'

type LaravelHeaderOptions = {
  includeTenant?: boolean
}

/**
 * Reads the value of the cookie with the given name from the request headers.
 *
 * @param {Request} req - The request object containing the headers.
 * @param {string} name - The name of the cookie.
 * @return {string | null} - The value of the cookie, or null if it doesn't exist.
 */
function readCookie(req: Request, name: string): string | null {
  // Get the raw cookie string from the request headers
  const raw = req.headers.get('cookie') ?? ''

  // Split the raw cookie string into an array of key-value pairs
  const found = raw
    .split(';')
    .map(v => v.trim())

    // Find the key-value pair that starts with the given name
    .find(v => v.startsWith(`${name}=`))

  // If the key-value pair exists, decode its value and return it. Otherwise, return null.
  return found ? decodeURIComponent(found.slice(name.length + 1)) : null
}

/**
 * Generates headers for a request to the Laravel backend, including the Accept header and an Authorization header
 * if an access token is present in the request cookies.
 *
 * @param {Request} req - The request object containing the cookies.
 * @param {HeadersInit} [extra={}] - Additional headers to include in the generated headers.
 * @param {LaravelHeaderOptions} [options={}] - Request header options.
 * @return {Headers} - The generated headers.
 */
export function laravelHeaders(req: Request, extra: HeadersInit = {}, options: LaravelHeaderOptions = {}): Headers {
  const headers = new Headers(extra)

  headers.set('Accept', 'application/json')
  headers.set('Referer', process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3001')

  const raw = req.headers.get('cookie') ?? ''

  if (raw) headers.set('Cookie', raw)

  if (options.includeTenant) {
    const currentTenantId = parseCurrentTenantId(readCookie(req, CURRENT_TENANT_COOKIE))

    if (currentTenantId) {
      headers.set('X-Tenant-ID', currentTenantId)
      headers.set('X-Company-ID', currentTenantId)
    }
  }

  return headers
}

export function laravelTenantHeaders(req: Request, extra: HeadersInit = {}): Headers {
  return laravelHeaders(req, extra, { includeTenant: true })
}

export function requireCurrentTenant(req: Request): NextResponse | null {
  const currentTenantId = parseCurrentTenantId(readCookie(req, CURRENT_TENANT_COOKIE))

  if (currentTenantId) {
    return null
  }

  // Company context is now resolved server-side from the authenticated user.
  // The legacy cookie remains a compatibility hint, not a hard requirement.
  return null
}
