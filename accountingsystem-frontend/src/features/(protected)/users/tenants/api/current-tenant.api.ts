import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import {
  currentTenantApiErrorSchema,
  currentTenantStateSchema,
  updateCurrentTenantInputSchema
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'

import type {
  CurrentTenantState,
  UpdateCurrentTenantInput
} from '@/features/(protected)/users/tenants/model/current-tenant.types'

export class CurrentTenantApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'CurrentTenantApiError'
    this.status = status
  }
}

export async function fetchCurrentTenant(): Promise<CurrentTenantState> {
  // Laravel exposes the current company via /api/v1/company (CompaniesController@current).
  // There is no /api/v1/tenants/current route — calling it would 404. The response
  // shape is { data: { id, company_id, name, ... } | null }, so we map it to the
  // { data: { tenantId } } shape the frontend schema expects.
  const res = await laravelBrowserClient.get<{ data: { id?: string; company_id?: string } | null }>('/api/v1/company')

  const company = res.data?.data ?? null
  const tenantId = company?.id ?? company?.company_id ?? null

  return currentTenantStateSchema.parse({ data: { tenantId } })
}

export async function updateCurrentTenant(input: unknown): Promise<CurrentTenantState> {
  const payload = updateCurrentTenantInputSchema.parse(input) as UpdateCurrentTenantInput

  const res = await laravelBrowserClient.put('/api/v1/tenants/current', payload)

  return currentTenantStateSchema.parse(res.data)
}
