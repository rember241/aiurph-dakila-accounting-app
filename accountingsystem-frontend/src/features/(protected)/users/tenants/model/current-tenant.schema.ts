import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'

export const CURRENT_TENANT_COOKIE = 'current_tenant_id'
export const CURRENT_TENANT_COOKIE_MAX_AGE = 60 * 60 * 8

export const currentTenantIdSchema = zUuid()
export const updateCurrentTenantInputSchema = z.object({
  tenantId: currentTenantIdSchema
})
export const currentTenantStateSchema = z.object({
  data: z.object({
    tenantId: currentTenantIdSchema.nullable()
  })
})
export const currentTenantApiErrorSchema = z.object({
  message: z.string().optional()
})

export const tenantAutoSelectResponseSchema = z.object({
  total: z.number().int().nonnegative(),
  data: z.array(
    z.object({
      id: currentTenantIdSchema
    })
  )
})

export function parseCurrentTenantId(value: unknown) {
  const parsed = currentTenantIdSchema.safeParse(value)

  return parsed.success ? parsed.data : null
}
