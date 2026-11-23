import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'

export const CORE_ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'] as const
export const ACCOUNT_STATUSES = ['active', 'inactive'] as const

export const accountCreateSchema = z.object({
  name: z.string().min(1, 'Name is required').max(120, 'Max 120 characters'),
  type: z.enum(CORE_ACCOUNT_TYPES),
  account_type_id: z.coerce.number().int().positive(),
  detail_type_id: z.coerce.number().int().positive(),
  parent_id: zUuid().optional().nullable(),
  status: z.enum(ACCOUNT_STATUSES).default('active'),
  is_posting: z.coerce.boolean().default(true),
  contra_account_id: zUuid().optional().nullable(),
  description: z.string().max(500, 'Max 500 characters').optional().nullable()
})

// Scalable foundation for later CRUD
export type AccountCreateFormValues = z.input<typeof accountCreateSchema>
export type AccountCreateSubmitValues = z.output<typeof accountCreateSchema>
