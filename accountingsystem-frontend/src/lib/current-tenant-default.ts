import { z } from 'zod'

import { currentTenantIdSchema } from '@/features/(protected)/users/tenants/model/current-tenant.schema'

const BASE = process.env.LARAVEL_API_URL

const currentCompanyResponseSchema = z.object({
  data: z
    .object({
      id: currentTenantIdSchema
    })
    .nullable()
})

export async function resolveDefaultTenantId(token: string) {
  if (!BASE) {
    return null
  }

  const res = await fetch(`${BASE}/api/v1/company`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`
    },
    cache: 'no-store'
  }).catch(() => null)

  if (!res?.ok) {
    return null
  }

  const payload = await res.json().catch(() => null)
  const parsed = currentCompanyResponseSchema.safeParse(payload)

  if (!parsed.success) {
    return null
  }

  return parsed.data.data?.id ?? null
}
