/* Should be no TenStack Implementation here */

import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  Paginated,
  AccountColumn,
  CoreType,
  AccountStatus,
  AccountUpsertPayload,
  AccountStatsResponse,
  AccountTreeResponse
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import {
  accountStatsResponseSchema,
  accountTreeResponseSchema,
  accountTypesResponseSchema,
  detailTypesResponseSchema,
  paginatedAccountsResponseSchema
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

export type AccountsQuery = {
  q?: string
  type?: CoreType | ''
  status?: AccountStatus | ''
  is_posting?: boolean
  nested?: boolean
  page?: number // 0-based in DataGrid
  pageSize?: number
  sortField?: string
  sortDir?: 'asc' | 'desc'
}

function toParams(q: AccountsQuery) {
  const p = new URLSearchParams()

  if (q.q) p.set('q', q.q)
  if (q.type) p.set('type', q.type)
  if (q.status) p.set('status', q.status)
  if (q.is_posting !== undefined) p.set('is_posting', q.is_posting.toString())
  if (q.nested !== undefined) p.set('nested', q.nested.toString())

  if (!q.nested) {
    p.set('page', String((q.page ?? 0) + 1)) // Laravel 1-based
    p.set('perPage', String(q.pageSize ?? 10))

    if (q.sortField) p.set('sortField', q.sortField)
    if (q.sortDir) p.set('sortDir', q.sortDir)
  }

  return p
}

export async function fetchAccounts(query: AccountsQuery) {
  const p = toParams(query)

  const res = await laravelBrowserClient.get('/api/v1/accounts', { params: Object.fromEntries(p) })

  const parsed = paginatedAccountsResponseSchema.parse(res.data)

  return {
    data: parsed.data,
    per_page: parsed.per_page ?? parsed.perPage ?? 10,
    current_page: parsed.current_page ?? parsed.page ?? 1,
    total: parsed.total
  } satisfies Paginated<AccountColumn>
}

export async function fetchAccountsTree(
  query: Omit<AccountsQuery, 'page' | 'pageSize' | 'sortField' | 'sortDir'> = {}
) {
  const p = toParams({ ...query, nested: true })

  const res = await laravelBrowserClient.get('/api/v1/accounts', { params: Object.fromEntries(p) })

  return accountTreeResponseSchema.parse(res.data) satisfies AccountTreeResponse
}

export async function createAccount(payload: AccountUpsertPayload) {
  const res = await laravelBrowserClient.post('/api/v1/accounts', payload)

  return res.data
}

export async function updateAccount(id: string, payload: AccountUpsertPayload) {
  const res = await laravelBrowserClient.put(`/api/v1/accounts/${id}`, payload)

  return res.data
}

export async function deleteAccount(id: string) {
  const res = await laravelBrowserClient.delete(`/api/v1/accounts/${id}`)

  return res.data
}

export async function fetchAccountStats() {
  const res = await laravelBrowserClient.get('/api/v1/accounts/stats')

  return accountStatsResponseSchema.parse(res.data) satisfies AccountStatsResponse
}

/**
 * TODO: The response type is not correct
 * I will fix this sooner
 * @talismantimpac
 */

export async function fetchAccountTypesByCoreType(coreType: string) {
  const res = await laravelBrowserClient.get('/api/v1/account-types', { params: { core_type: coreType } })

  return accountTypesResponseSchema.parse(res.data)
}

export async function fetchDetailTypesByAccountType(accountTypeId: number) {
  const res = await laravelBrowserClient.get(`/api/v1/account-types/${accountTypeId}/detail-types`)

  return detailTypesResponseSchema.parse(res.data)
}
