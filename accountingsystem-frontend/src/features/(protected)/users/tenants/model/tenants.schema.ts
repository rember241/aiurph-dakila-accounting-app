import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import { TENANT_SORT_DIRECTIONS, TENANT_SORT_FIELDS, TENANT_STATUSES } from './tenants.types'

const tenantSlugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

const numericIdSchema = z
  .union([z.number().int().positive(), z.string().regex(/^\d+$/)])
  .transform(value => Number(value))

export function isValidTimeZone(timeZone: string) {
  try {
    Intl.DateTimeFormat(undefined, { timeZone }).format(new Date())

    return true
  } catch {
    return false
  }
}

export const createTenantFormSchema = z.object({
  name: z.string().trim().min(2, 'Company name is required').max(160, 'Max 160 characters'),
  slug: z
    .string()
    .trim()
    .max(160, 'Max 160 characters')
    .refine(value => value === '' || tenantSlugPattern.test(value), 'Use lowercase letters, numbers, and hyphens only'),
  timezone: z
    .string()
    .trim()
    .min(1, 'Timezone is required')
    .refine(value => isValidTimeZone(value), 'Select a valid timezone')
})

export const createTenantInputSchema = createTenantFormSchema.transform(values => ({
  name: values.name.trim(),
  slug: values.slug.trim() || undefined,
  timezone: values.timezone.trim()
}))

export const tenantRoleSummarySchema = z.object({
  id: zUuid(),
  tenant_id: zUuid().nullable(),
  key: z.string().min(1),
  name: z.string().min(1),
  status: z.string().min(1)
})

export const tenantOwnerSchema = z.object({
  id: numericIdSchema,
  name: z.string().min(1),
  email: z.email()
})

export const tenantOwnerMembershipSchema = z.object({
  id: zUuid(),
  user_id: numericIdSchema,
  status: z.string().min(1),
  joined_at: z.string().nullable(),
  roles: z.array(tenantRoleSummarySchema)
})

export const tenantRecordSchema = z.object({
  id: zUuid(),
  name: z.string().min(1),
  slug: z.string().min(1),
  status: z.enum(TENANT_STATUSES),
  timezone: z.string().min(1),
  owner_user_id: z.string().nullable(),
  owner: tenantOwnerSchema.nullable(),
  owner_membership: tenantOwnerMembershipSchema.nullable(),
  created_at: z.string(),
  updated_at: z.string()
})

export const tenantsQuerySchema = z.object({
  q: z.string().trim().max(160).optional(),
  status: z.enum(TENANT_STATUSES).or(z.literal('')).optional(),
  page: z.number().int().min(0).optional(),
  perPage: z.number().int().min(5).max(100).optional(),
  sortField: z.enum(TENANT_SORT_FIELDS).optional(),
  sortDir: z.enum(TENANT_SORT_DIRECTIONS).optional()
})

export const tenantsResponseSchema = z.object({
  data: z.array(tenantRecordSchema),
  page: z.number().int().positive(),
  perPage: z.number().int().positive(),
  total: z.number().int().nonnegative()
})

export const createTenantResponseSchema = z.object({
  data: tenantRecordSchema,
  message: z.string()
})

export const tenantApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})
