import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'

export type CoreType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
export const ACCOUNT_CATEGORY_FILTERS = [
  { value: 'bank', label: 'Bank', icon: 'bx-bxs-bank' },
  { value: 'credit_card', label: 'Credit Card', icon: 'bx-credit-card' },
  { value: 'undeposited_cash', label: 'Undeposited Cash', icon: 'bx-money' }
] as const

export type AccountCategory = (typeof ACCOUNT_CATEGORY_FILTERS)[number]['value']
export type AccountFilter = CoreType | AccountCategory | ''
export type AccountStatus = 'active' | 'inactive'
export type Contra = boolean
export type NormalBalance = 'debit' | 'credit'
export type AccountPosting = undefined | boolean
export type StatementGroup = 'balance_sheet' | 'income_statement'

export type CoaAccountTypeOption = {
  id: number
  code: string
  name: string
  core_type: CoreType | null
}

export type CoaDetailTypeOption = {
  id: number
  code: string
  name: string
  account_type_id: number
}

export type AccountColumn = {
  id: string
  code: string
  name: string
  type: CoreType
  account_type_id?: number | null
  account_type_name?: string | null
  detail_type_id?: number | null
  detail_type_name?: string | null
  normal_balance: NormalBalance
  parent_id: string | null
  is_posting: boolean
  status: AccountStatus
  is_contra: boolean
  statement_group: StatementGroup
  description?: string | null
  is_system: boolean
  level?: number
  has_children?: boolean
  created_at: string
  updated_at: string
}

export type AccountTreeNode = AccountColumn & {
  children: AccountTreeNode[]
}

export type Paginated<T> = {
  data: T[]
  per_page: number
  current_page: number
  total: number
}

export type AccountStats = {
  total: number
  posting: number
  nonPosting: number
  active: number
  inactive: number
  byType: Record<'asset' | 'liability' | 'equity' | 'revenue' | 'expense', number>
}

export type AccountStatsResponse = {
  data: AccountStats
}

export type AccountTreeResponse = {
  data: AccountTreeNode[]
  total: number
}

export type AccountUpsertPayload = {
  name: string
  type: CoreType
  account_type_id: number | null
  detail_type_id: number | null
  parent_id: string | null
  is_posting: AccountPosting
  status: AccountStatus
  contra_account_id?: string | null
  description?: string | null
}

export type AccountGroup = AccountColumn & { coreType: string }

export type AccountTransaction = {
  id: string
  date: string
  journalNumber: string
  description: string
  debit: number
  credit: number
  balance: number
}

export const ACCOUNT_CORE_TYPE_ORDER: CoreType[] = ['asset', 'liability', 'equity', 'revenue', 'expense']

export const ACCOUNT_CORE_TYPE_LABELS: Record<CoreType, string> = {
  asset: 'Assets',
  liability: 'Liabilities',
  equity: 'Equity',
  revenue: 'Revenue',
  expense: 'Expenses'
}

export function getAccountCoreTypeLabel(type: CoreType | string): string {
  return ACCOUNT_CORE_TYPE_LABELS[type as CoreType] ?? 'Other Accounts'
}

export function getAccountCoreTypeOrder(type: CoreType | string): number {
  const index = ACCOUNT_CORE_TYPE_ORDER.indexOf(type as CoreType)

  return index === -1 ? ACCOUNT_CORE_TYPE_ORDER.length : index
}

export function compareAccountsByCoreTypeAndCode(
  a: Pick<AccountColumn, 'type' | 'code' | 'name'>,
  b: Pick<AccountColumn, 'type' | 'code' | 'name'>
): number {
  const typeOrder = getAccountCoreTypeOrder(a.type) - getAccountCoreTypeOrder(b.type)

  if (typeOrder !== 0) return typeOrder

  const codeOrder = a.code.localeCompare(b.code, undefined, { numeric: true, sensitivity: 'base' })

  if (codeOrder !== 0) return codeOrder

  return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
}

export const coreTypeSchema = z.enum(['asset', 'liability', 'equity', 'revenue', 'expense'])
export const accountStatusSchema = z.enum(['active', 'inactive'])
export const normalBalanceSchema = z.enum(['debit', 'credit'])
export const statementGroupSchema = z.enum(['balance_sheet', 'income_statement'])

export const accountColumnSchema = z.object({
  id: zUuid(),
  code: z.string(),
  name: z.string(),
  type: coreTypeSchema,
  account_type_id: z.number().int().nullable().optional(),
  account_type_name: z.string().nullable().optional(),
  detail_type_id: z.number().int().nullable().optional(),
  detail_type_name: z.string().nullable().optional(),
  normal_balance: normalBalanceSchema,
  parent_id: zUuid().nullable(),
  is_posting: z.boolean(),
  status: accountStatusSchema,
  is_contra: z.boolean(),
  statement_group: statementGroupSchema,
  description: z.string().nullable().optional(),
  is_system: z.boolean(),
  level: z.number().int().optional(),
  has_children: z.boolean().optional(),
  created_at: z.string(),
  updated_at: z.string()
})

export type ParsedAccountColumn = z.output<typeof accountColumnSchema>

export const paginatedAccountsResponseSchema = z.object({
  data: z.array(accountColumnSchema),
  per_page: z.number().int().optional(),
  perPage: z.number().int().optional(),
  current_page: z.number().int().optional(),
  page: z.number().int().optional(),
  total: z.number().int()
})

export const accountTreeNodeSchema: z.ZodType<AccountTreeNode> = accountColumnSchema.extend({
  children: z.lazy(() => z.array(accountTreeNodeSchema))
})

export const accountTreeResponseSchema = z.object({
  data: z.array(accountTreeNodeSchema),
  total: z.number().int()
})

export const accountStatsResponseSchema = z.object({
  data: z.object({
    total: z.number().int().nonnegative(),
    posting: z.number().int().nonnegative(),
    nonPosting: z.number().int().nonnegative(),
    active: z.number().int().nonnegative(),
    inactive: z.number().int().nonnegative(),
    byType: z.preprocess(
      value => (Array.isArray(value) ? {} : value),
      z.object({
        asset: z.number().int().nonnegative().default(0),
        liability: z.number().int().nonnegative().default(0),
        equity: z.number().int().nonnegative().default(0),
        revenue: z.number().int().nonnegative().default(0),
        expense: z.number().int().nonnegative().default(0)
      })
    )
  })
})

export const accountTypesResponseSchema = z.object({
  data: z.array(
    z.object({
      id: z.number().int(),
      code: z.string().optional(),
      name: z.string(),
      core_type: coreTypeSchema.nullable().optional()
    })
  )
})

export const detailTypesResponseSchema = z.object({
  data: z.array(
    z.object({
      id: z.number().int(),
      code: z.string().optional(),
      name: z.string(),
      account_type_id: z.number().int().optional()
    })
  )
})
