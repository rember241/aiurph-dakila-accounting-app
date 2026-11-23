export type FiscalYearStatus = 'open' | 'closed'
export type PeriodStatus = 'open' | 'soft_closed' | 'closed'

export type FiscalYear = {
  id: string
  code: string
  name: string
  start_date: string
  end_date: string
  status: FiscalYearStatus
  is_current: boolean
  closed_at: string | null
  periods_count?: number
  closed_periods_count?: number
  periods?: AccountingPeriod[]
  created_at: string
  updated_at: string
}

export type AccountingPeriod = {
  id: string
  fiscal_year_id: string
  period_number: number
  name: string
  start_date: string
  end_date: string
  status: PeriodStatus
  lock_ap: boolean
  lock_ar: boolean
  lock_inventory: boolean
  closed_at: string | null
  fiscal_year?: FiscalYear
  created_at: string
  updated_at: string
}

export type PaginatedPeriodResponse<T> = {
  data: T[]
  page: number
  total: number
  per_page: number
  last_page: number
}

export type FiscalYearsResponse = PaginatedPeriodResponse<FiscalYear>
export type AccountingPeriodsResponse = PaginatedPeriodResponse<AccountingPeriod>

export type CreateFiscalYearPayload = {
  code: string
  name: string
  start_date: string
  end_date: string
  is_current?: boolean
}

export type SetCurrentFiscalYearResponse = FiscalYear
export type GetCurrentFiscalYearResponse = FiscalYear | null

export type FiscalYearsQuery = {
  status?: FiscalYearStatus | ''
  search?: string
  is_current?: boolean
  page?: number // 0-based in frontend
  pageSize?: number
}

export type AccountingPeriodsQuery = {
  fiscal_year_id?: string
  status?: PeriodStatus | ''
  search?: string
  page?: number // 0-based in frontend
  pageSize?: number
  isPaginated?: boolean
}

export type TransitionPeriodStatusPayload = {
  status: PeriodStatus
}

export type UpdateFiscalYearPayload = {
  code: string
  name: string
  status: FiscalYearStatus
}

export function toTransitionPeriodStatusPayload(values: TransitionPeriodStatusPayload): TransitionPeriodStatusPayload {
  return {
    status: values.status
  }
}

export function toUpdateFiscalYearPayload(values: UpdateFiscalYearPayload): UpdateFiscalYearPayload {
  return {
    code: values.code,
    name: values.name,
    status: values.status
  }
}

export type TransitionPeriodStatusResponse = {
  data: AccountingPeriod
  message: string
}

export type UpdateFiscalYearResponse = {
  data: FiscalYear
  message: string
}

export type DeleteFiscalYearResponse = {
  message: string
}

export type PeriodManagementKpiResponse = {
  current_fiscal_year: FiscalYear | null
  days_left_in_current_year: number
  closing_progress: {
    closed_periods: number
    total_periods: number
    percentage: number
  }
}

export type PeriodManagementKpiApiResponse = {
  data: PeriodManagementKpiResponse
  message: string
}
