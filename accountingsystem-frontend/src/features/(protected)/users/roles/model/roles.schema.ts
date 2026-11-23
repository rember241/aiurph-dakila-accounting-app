import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import {
  ROLE_ACCESS_LEVELS,
  ROLE_SORT_DIRECTIONS,
  ROLE_SORT_FIELDS,
  ROLE_STATUSES
} from '@/features/(protected)/users/roles/model/roles.types'
import { ROLE_PERMISSION_SCOPES } from '@/features/(protected)/users/roles/model/role-access-control'
import { PERMISSION_KEYS } from '@/features/(protected)/users/permissions'

export const roleFormSchema = z
  .object({
    name: z.string().trim().min(2, 'Role name is required').max(100, 'Max 100 characters'),
    key: z
      .string()
      .trim()
      .min(2, 'Role key is required')
      .max(60, 'Max 60 characters')
      .regex(/^[A-Z0-9_]+$/, 'Use uppercase letters, numbers, and underscore only'),
    orgUnitId: zUuid().or(z.literal('')),
    status: z.enum(ROLE_STATUSES),
    accessLevel: z.enum(ROLE_ACCESS_LEVELS),
    permissionScope: z.enum(ROLE_PERMISSION_SCOPES),
    modulePermissions: z.record(z.string(), z.array(z.enum(PERMISSION_KEYS))),
    description: z.string().trim().min(3, 'Description is required').max(240, 'Max 240 characters'),
    permissions: z.array(z.enum(PERMISSION_KEYS)).min(1, 'Select at least one permission')
  })
  .refine(
    values =>
      values.permissionScope === 'all' || Object.values(values.modulePermissions).some(actions => actions.length > 0),
    {
      message: 'Select at least one page permission',
      path: ['modulePermissions']
    }
  )

export const roleListQuerySchema = z.object({
  q: z.string().trim().max(120).optional(),
  status: z.enum(ROLE_STATUSES).or(z.literal('')).optional(),
  orgUnitId: zUuid().or(z.literal('')).optional(),
  page: z.number().int().min(0).optional(),
  perPage: z.number().int().min(5).max(100).optional(),
  sortField: z.enum(ROLE_SORT_FIELDS).optional(),
  sortDir: z.enum(ROLE_SORT_DIRECTIONS).optional()
})

export const roleApiRecordSchema = z.object({
  id: zUuid(),
  tenant_id: zUuid().nullable(),
  system_generated: z.boolean().default(false),
  role_type: z.enum(['system', 'custom']).default('custom'),
  is_admin_role: z.boolean().default(false),
  can_edit: z.boolean().default(true),
  can_delete: z.boolean().default(true),
  can_deactivate: z.boolean().default(true),
  key: z.string().min(1),
  name: z.string().min(1),
  department: z.string().nullable(),
  org_unit_id: zUuid().nullable().optional(),
  org_unit: z
    .object({
      id: zUuid(),
      company_id: zUuid(),
      parent_org_unit_id: zUuid().nullable(),
      type: z.enum(['head_office', 'branch', 'department', 'team']),
      name: z.string().min(1),
      status: z.enum(['active', 'inactive']),
      is_root: z.boolean()
    })
    .nullable()
    .optional(),
  status: z.enum(['active', 'inactive']),
  access_level: z.enum(ROLE_ACCESS_LEVELS),
  permission_scope: z.enum(['global', 'department', 'own']),
  module_permissions: z.record(z.string(), z.array(z.enum(PERMISSION_KEYS))).default({}),
  description: z.string().nullable(),
  members_count: z.number().int().nonnegative().optional(),
  created_at: z.string(),
  updated_at: z.string()
})

export const rolesResponseSchema = z.object({
  data: z.array(roleApiRecordSchema),
  page: z.number().int().positive(),
  perPage: z.number().int().positive(),
  total: z.number().int().nonnegative()
})

export const roleResponseSchema = z.object({
  data: roleApiRecordSchema,
  message: z.string().optional()
})

export const roleStatsResponseSchema = z.object({
  data: z.object({
    total: z.number().int().nonnegative(),
    active: z.number().int().nonnegative(),
    inactive: z.number().int().nonnegative()
  })
})

export const roleApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})

export type RoleFormValues = z.input<typeof roleFormSchema>
export type RoleFormSubmitValues = z.output<typeof roleFormSchema>
