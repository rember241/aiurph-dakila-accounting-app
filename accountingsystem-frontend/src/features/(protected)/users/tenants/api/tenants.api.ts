import type { z } from 'zod'

import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import {
  createTenantInputSchema,
  createTenantResponseSchema,
  tenantApiErrorSchema,
  tenantsQuerySchema,
  tenantsResponseSchema
} from '@/features/(protected)/users/tenants/model/tenants.schema'

import type {
  CreateTenantResponse,
  TenantApiFieldErrors,
  TenantQuery,
  TenantsResponse
} from '@/features/(protected)/users/tenants/model/tenants.types'

export class TenantApiError extends Error {
  readonly status: number
  readonly fieldErrors: TenantApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: TenantApiFieldErrors | null = null) {
    super(message)
    this.name = 'TenantApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

function toParams(query: TenantQuery) {
  const parsed = tenantsQuerySchema.parse(query)
  const params = new URLSearchParams()

  if (parsed.q) params.set('q', parsed.q)
  if (parsed.status) params.set('status', parsed.status)
  params.set('page', String((parsed.page ?? 0) + 1))
  params.set('perPage', String(parsed.perPage ?? 5))

  if (parsed.sortField) params.set('sortField', parsed.sortField)
  if (parsed.sortDir) params.set('sortDir', parsed.sortDir)

  return params
}

export async function fetchTenants(query: TenantQuery): Promise<TenantsResponse> {
  const params = toParams(query)

  const res = await laravelBrowserClient.get('/api/v1/tenants', { params: Object.fromEntries(params) })

  return tenantsResponseSchema.parse(res.data)
}

export async function createTenant(input: unknown): Promise<CreateTenantResponse> {
  const payload = createTenantInputSchema.parse(input)

  const res = await laravelBrowserClient.post('/api/v1/tenants', payload)

  return createTenantResponseSchema.parse(res.data)
}
