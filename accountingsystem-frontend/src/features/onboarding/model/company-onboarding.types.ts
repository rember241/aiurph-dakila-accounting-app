import type { TenantRecord } from '@/features/(protected)/users/tenants/model/tenants.types'

export type OnboardingFiscalYearRecord = {
  id: string
  code: string
  name: string
  start_date: string
  end_date: string
  status: 'open' | 'closed'
  is_current: boolean
  closed_at: string | null
  periods_count?: number
  closed_periods_count?: number
  periods?: unknown[]
  created_at: string
  updated_at: string
}

export type OnboardingRootOrgUnitRecord = {
  id: string
  company_id: string
  tenant_id: string
  parent_org_unit_id: string | null
  type: 'head_office' | 'branch' | 'department' | 'team'
  name: string
  code: string
  status: 'active' | 'inactive'
  is_root: boolean
  created_at: string
  updated_at: string
}

export type CompanyOnboardingFormValues = {
  companyName: string
  companySlug: string
  companyTimezone: string
  createRootOrgUnit: boolean
  rootOrgUnitName: string
  fiscalYearCode: string
  fiscalYearName: string
  fiscalYearStartDate: string
  fiscalYearEndDate: string
  accountsSetupMode: 'generated' | 'empty'
}

export type CompanyOnboardingResponse = {
  data: {
    tenant: TenantRecord
    organization: {
      root_org_unit: OnboardingRootOrgUnitRecord | null
    }
    fiscal_year: OnboardingFiscalYearRecord
    accounts_setup: {
      mode: 'generated' | 'empty'
      created_count: number
    }
  }
  message: string
}

export type CompanyOnboardingApiFieldErrors = Record<string, string[] | undefined>
