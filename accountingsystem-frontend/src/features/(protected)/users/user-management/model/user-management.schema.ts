import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import {
  orgUnitStatusSchema,
  orgUnitTypeSchema
} from '@/features/(protected)/users/company-structure/model/company-structure.schema'
import { PERMISSION_KEYS } from '@/features/(protected)/users/permissions'
import {
  USER_ACCESS_LEVELS,
  USER_ROLE_ASSIGNMENT_SCOPES,
  USER_SORT_DIRECTIONS,
  USER_SORT_FIELDS,
  USER_STATUSES
} from '@/features/(protected)/users/user-management/model/user-management.types'

export const userRoleAssignmentScopeSchema = z.enum(USER_ROLE_ASSIGNMENT_SCOPES)

export const userOrgUnitSummarySchema = z
  .object({
    id: zUuid(),
    name: z.string().min(1),
    code: z.string().trim().optional(),
    type: orgUnitTypeSchema,
    status: orgUnitStatusSchema
  })
  .transform(data => ({
    ...data,
    code: data.code && data.code.trim().length > 0 ? data.code.trim() : data.name
  }))

export const userFormSchema = z
  .object({
    fullName: z.string().trim().min(2, 'Full name is required').max(120, 'Max 120 characters'),
    email: z.email('Invalid email').max(120, 'Max 120 characters'),
    password: z
      .string()
      .trim()
      .max(255, 'Max 255 characters')
      .refine(value => value === '' || value.length >= 8, 'Password must be at least 8 characters'),
    roleIds: z.array(zUuid()).min(1, 'Select a role').max(1, 'Select only one role'),
    primaryOrgUnitId: z.string().uuid('Select a primary org unit'),
    additionalOrgUnitIds: z.array(zUuid()).default([]),
    roleAssignmentScope: userRoleAssignmentScopeSchema,
    scopeOrgUnitIds: z.array(zUuid()).default([]),
    status: z.enum(USER_STATUSES)
  })
  .superRefine((data, ctx) => {
    if (data.additionalOrgUnitIds.includes(data.primaryOrgUnitId)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['additionalOrgUnitIds'],
        message: 'Additional org units cannot include the primary org unit.'
      })
    }

    const uniqueAdditionalOrgUnits = new Set(data.additionalOrgUnitIds)

    if (uniqueAdditionalOrgUnits.size !== data.additionalOrgUnitIds.length) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['additionalOrgUnitIds'],
        message: 'Additional org units must be unique.'
      })
    }

    if (data.roleAssignmentScope === 'selected_org_units' && data.scopeOrgUnitIds.length === 0) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['scopeOrgUnitIds'],
        message: 'Select at least one scoped org unit.'
      })
    }

    const assignedOrgUnits = new Set([data.primaryOrgUnitId, ...data.additionalOrgUnitIds])

    if (data.scopeOrgUnitIds.some(orgUnitId => !assignedOrgUnits.has(orgUnitId))) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['scopeOrgUnitIds'],
        message: 'Scoped org units must come from the assigned org unit list.'
      })
    }
  })

const numericIdSchema = z
  .union([z.number().int().positive(), z.string().regex(/^\d+$/)])
  .transform(value => String(value))

export const userRoleApiSchema = z.object({
  id: zUuid(),
  key: z.string().min(1),
  name: z.string().min(1),
  status: z.enum(['active', 'inactive']),
  access_level: z.enum(USER_ACCESS_LEVELS),
  module_permissions: z.record(z.string(), z.array(z.enum(PERMISSION_KEYS))).default({})
})

export const userApiRecordSchema = z.object({
  id: numericIdSchema,
  name: z.string().min(1),
  email: z.email(),
  is_owner: z.boolean().default(false),
  is_current_user: z.boolean().default(false),
  can_edit: z.boolean().default(true),
  can_delete: z.boolean().default(true),
  status: z.enum(['active', 'inactive']),
  department: z.string().nullable(),
  access_level: z.enum(USER_ACCESS_LEVELS),
  avatar_url: z.string().nullable(),
  last_active_at: z.string().nullable(),
  tenant_membership: z
    .object({
      id: zUuid(),
      tenant_id: zUuid(),
      status: z.enum(['active', 'inactive']),
      joined_at: z.string().nullable()
    })
    .nullable(),
  roles: z.array(userRoleApiSchema),
  primary_org_unit: userOrgUnitSummarySchema.nullable().optional(),
  additional_org_units: z.array(userOrgUnitSummarySchema).optional().default([]),
  role_assignment_scope: userRoleAssignmentScopeSchema.optional().default('company'),
  scope_org_units: z.array(userOrgUnitSummarySchema).optional().default([]),
  created_at: z.string(),
  updated_at: z.string()
})

export const usersResponseSchema = z.object({
  data: z.array(userApiRecordSchema),
  page: z.number().int().positive(),
  perPage: z.number().int().positive(),
  total: z.number().int().nonnegative()
})

export const userResponseSchema = z.object({
  data: userApiRecordSchema,
  message: z.string().optional()
})

export const userStatsResponseSchema = z.object({
  data: z.object({
    total: z.number().int().nonnegative(),
    active: z.number().int().nonnegative(),
    inactive: z.number().int().nonnegative()
  })
})

export const userQuerySchema = z.object({
  q: z.string().trim().max(120).optional(),
  status: z.enum(USER_STATUSES).or(z.literal('')).optional(),
  department: z.string().trim().max(120).optional(),
  roleId: zUuid().optional(),
  page: z.number().int().min(0).optional(),
  perPage: z.number().int().min(5).max(100).optional(),
  sortField: z.enum(USER_SORT_FIELDS).optional(),
  sortDir: z.enum(USER_SORT_DIRECTIONS).optional()
})

export const userApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})

export const userMutationPayloadSchema = z
  .object({
    name: z.string().trim().min(2, 'Full name is required').max(120, 'Max 120 characters'),
    email: z.email('Invalid email').max(120, 'Max 120 characters'),
    password: z
      .string()
      .trim()
      .min(8, 'Password must be at least 8 characters')
      .max(255, 'Max 255 characters')
      .optional(),
    role_ids: z.array(zUuid()).min(1, 'Select a role').max(1, 'Select only one role'),
    primary_org_unit_id: z.string().uuid('Select a primary org unit'),
    additional_org_unit_ids: z.array(zUuid()).default([]),
    role_assignment_scope: userRoleAssignmentScopeSchema,
    scope_org_unit_ids: z.array(zUuid()).default([]),
    status: z.enum(['active', 'inactive'])
  })
  .superRefine((data, ctx) => {
    if (data.additional_org_unit_ids.includes(data.primary_org_unit_id)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['additional_org_unit_ids'],
        message: 'Additional org units cannot include the primary org unit.'
      })
    }

    if (data.role_assignment_scope === 'selected_org_units' && data.scope_org_unit_ids.length === 0) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['scope_org_unit_ids'],
        message: 'Select at least one scoped org unit.'
      })
    }
  })

export type UserFormValues = z.input<typeof userFormSchema>
export type UserFormSubmitValues = z.output<typeof userFormSchema>
