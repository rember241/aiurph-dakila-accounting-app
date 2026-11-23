import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'

const codePattern = /^[A-Z0-9-]+$/

export const orgUnitTypeSchema = z.enum(['head_office', 'branch', 'department', 'team'])
export const orgUnitStatusSchema = z.enum(['active', 'inactive'])

export const orgUnitRecordSchema = z.object({
  id: zUuid(),
  companyId: zUuid(),
  parentId: zUuid().nullable(),
  name: z.string().min(1),
  code: z.string().min(1),
  type: orgUnitTypeSchema,
  status: orgUnitStatusSchema,
  isRoot: z.boolean(),
  membersCount: z.number().int().nonnegative(),
  linkedRolesCount: z.number().int().nonnegative(),
  location: z.string().nullable(),
  description: z.string().nullable(),
  createdAt: z.string().min(1),
  updatedAt: z.string().min(1)
})

export type OrgUnitTreeNodeSchema = z.infer<typeof orgUnitRecordSchema> & {
  children: OrgUnitTreeNodeSchema[]
  depth: number
  directChildrenCount: number
}

export const orgUnitTreeNodeSchema: z.ZodType<OrgUnitTreeNodeSchema> = orgUnitRecordSchema.extend({
  children: z.lazy(() => orgUnitTreeNodeSchema.array()),
  depth: z.number().int().min(0),
  directChildrenCount: z.number().int().nonnegative()
})

export const companyStructureStatsSchema = z.object({
  total: z.number().int().nonnegative(),
  active: z.number().int().nonnegative(),
  inactive: z.number().int().nonnegative(),
  headOffice: z.number().int().nonnegative(),
  branches: z.number().int().nonnegative(),
  departments: z.number().int().nonnegative(),
  teams: z.number().int().nonnegative()
})

export const companyStructureResponseSchema = z.object({
  data: z.object({
    units: orgUnitRecordSchema.array(),
    hierarchy: orgUnitTreeNodeSchema.array(),
    stats: companyStructureStatsSchema
  })
})

export const orgUnitMutationResponseSchema = z.object({
  data: orgUnitRecordSchema,
  message: z.string()
})

export const orgUnitApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})

export const orgUnitFormSchema = z
  .object({
    name: z.string().trim().min(2, 'Org unit name is required.').max(120, 'Maximum 120 characters.'),
    code: z
      .string()
      .trim()
      .toUpperCase()
      .min(2, 'Code is required.')
      .max(24, 'Maximum 24 characters.')
      .regex(codePattern, 'Use uppercase letters, numbers, and hyphens only.'),
    type: orgUnitTypeSchema,
    parentId: zUuid().nullable(),
    location: z.string().trim().max(120, 'Maximum 120 characters.'),
    description: z.string().trim().max(240, 'Maximum 240 characters.')
  })
  .superRefine((data, ctx) => {
    if (data.parentId === null && data.type !== 'head_office' && data.type !== 'branch') {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['parentId'],
        message: 'Select a parent unit for this org unit type.'
      })
    }
  })

export const orgUnitFormInputSchema = z.object({
  name: z.string().trim().min(2, 'Org unit name is required.').max(120, 'Maximum 120 characters.'),
  code: z
    .string()
    .trim()
    .toUpperCase()
    .min(2, 'Code is required.')
    .max(24, 'Maximum 24 characters.')
    .regex(codePattern, 'Use uppercase letters, numbers, and hyphens only.'),
  type: orgUnitTypeSchema,
  parentId: zUuid().or(z.literal('')).nullable().transform(value => (value ? value : null)),
  location: z
    .string()
    .trim()
    .max(120, 'Maximum 120 characters.')
    .optional()
    .transform(value => value ?? ''),
  description: z
    .string()
    .trim()
    .max(240, 'Maximum 240 characters.')
    .optional()
    .transform(value => value ?? '')
})

export const orgUnitStatusInputSchema = z.object({
  status: orgUnitStatusSchema
})
