import { cache } from 'react'

import { redirect } from 'next/navigation'
import { cookies } from 'next/headers'

import { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'
import {
  CURRENT_TENANT_COOKIE,
  parseCurrentTenantId
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'

const BASE = process.env.LARAVEL_API_URL

const resolveServerCurrentTenantId = cache(async () => {
  const cookieStore = await cookies()
  const currentTenantId = parseCurrentTenantId(cookieStore.get(CURRENT_TENANT_COOKIE)?.value)

  if (currentTenantId) {
    return currentTenantId
  }

  const token = cookieStore.get('access_token')?.value

  if (!BASE || !token) {
    return null
  }

  const response = await fetch(`${BASE}/api/v1/company`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`
    },
    cache: 'no-store'
  }).catch(() => null)

  if (!response?.ok) {
    return null
  }

  const payload = await response.json().catch(() => null)

  return parseCurrentTenantId(payload?.data?.id)
})

export async function getServerCurrentTenantId() {
  return resolveServerCurrentTenantId()
}

export async function requireServerCurrentTenantId(redirectTo = DEFAULT_PROTECTED_REDIRECT) {
  const currentTenantId = await getServerCurrentTenantId()

  if (!currentTenantId) {
    redirect(redirectTo)
  }

  return currentTenantId
}
