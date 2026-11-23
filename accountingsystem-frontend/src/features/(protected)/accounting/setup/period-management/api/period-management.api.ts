import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  AccountingPeriodsQuery,
  AccountingPeriodsResponse,
  CreateFiscalYearPayload,
  DeleteFiscalYearResponse,
  FiscalYearsQuery,
  FiscalYearsResponse,
  GetCurrentFiscalYearResponse,
  PeriodManagementKpiApiResponse,
  PeriodManagementKpiResponse,
  SetCurrentFiscalYearResponse,
  UpdateFiscalYearPayload,
  UpdateFiscalYearResponse,
  TransitionPeriodStatusPayload,
  TransitionPeriodStatusResponse,
  AccountingPeriod
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

function toFiscalYearsParams(query: FiscalYearsQuery) {
  const params = new URLSearchParams()
  const search = query.search?.trim()

  if (search) params.set('search', search)
  if (query.status) params.set('status', query.status)
  if (query.is_current !== undefined) params.set('is_current', query.is_current.toString())
  params.set('page', String((query.page ?? 0) + 1)) // Laravel 1-based
  params.set('pageSize', String(query.pageSize ?? 10))

  return params
}

function toAccountingPeriodsParams(query: AccountingPeriodsQuery) {
  const params = new URLSearchParams()
  const search = query.search?.trim()

  if (search) params.set('search', search)
  if (query.fiscal_year_id) params.set('fiscal_year_id', query.fiscal_year_id)
  if (query.status) params.set('status', query.status)
  if (query.isPaginated) params.set('isPaginated', String(query.isPaginated))
  params.set('page', String((query.page ?? 0) + 1)) // Laravel 1-based
  params.set('pageSize', String(query.pageSize ?? 10))

  return params
}

// Fiscal Year Functions

export async function fetchFiscalYears(query: FiscalYearsQuery) {
  const params = toFiscalYearsParams(query)

  const res = await laravelBrowserClient.get(`/api/v1/accounting/period-management/fiscal-years?${params.toString()}`)

  return res.data as FiscalYearsResponse
}

export async function createFiscalYear(payload: CreateFiscalYearPayload) {
  const res = await laravelBrowserClient.post('/api/v1/accounting/period-management/fiscal-years', payload)

  return res.data
}

export async function setCurrentFiscalYear(id: string) {
  const res = await laravelBrowserClient.post(`/api/v1/accounting/period-management/fiscal-years/${id}/set-current`)

  return res.data as SetCurrentFiscalYearResponse
}

export async function getCurrentFiscalYear() {
  const res = await laravelBrowserClient.get('/api/v1/accounting/period-management/fiscal-years/current')

  return res.data as GetCurrentFiscalYearResponse
}

export async function updateFiscalYear(id: string, payload: UpdateFiscalYearPayload) {
  const res = await laravelBrowserClient.patch(`/api/v1/accounting/period-management/fiscal-years/${id}`, payload)

  return res.data as UpdateFiscalYearResponse
}

export async function deleteFiscalYear(id: string) {
  const res = await laravelBrowserClient.delete(`/api/v1/accounting/period-management/fiscal-years/${id}`)

  return res.data as DeleteFiscalYearResponse
}

export async function fetchPeriodManagementKpi() {
  const res = await laravelBrowserClient.get('/api/v1/accounting/period-management/kpi')

  const payload = res.data as PeriodManagementKpiApiResponse

  return payload.data as PeriodManagementKpiResponse
}

// Per period Functions

export async function fetchAccountingPeriods(query: AccountingPeriodsQuery) {
  const params = toAccountingPeriodsParams(query)

  const res = await laravelBrowserClient.get(`/api/v1/accounting/period-management/periods?${params.toString()}`)

  if (query.isPaginated !== undefined && !query.isPaginated) return res.data as AccountingPeriod[]

  return res.data as AccountingPeriodsResponse
}

export async function transitionAccountingPeriodStatus(id: string, payload: TransitionPeriodStatusPayload) {
  const res = await laravelBrowserClient.patch(`/api/v1/accounting/period-management/periods/${id}/status`, payload)

  return res.data as TransitionPeriodStatusResponse
}
