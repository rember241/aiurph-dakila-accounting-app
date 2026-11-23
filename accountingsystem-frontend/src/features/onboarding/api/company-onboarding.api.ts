import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import {
  companyOnboardingApiErrorSchema,
  companyOnboardingFormSchema,
  companyOnboardingResponseSchema
} from '@/features/onboarding/model/company-onboarding.schema'

import type {
  CompanyOnboardingApiFieldErrors,
  CompanyOnboardingResponse
} from '@/features/onboarding/model/company-onboarding.types'

export class CompanyOnboardingApiError extends Error {
  readonly status: number
  readonly fieldErrors: CompanyOnboardingApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: CompanyOnboardingApiFieldErrors | null = null) {
    super(message)
    this.name = 'CompanyOnboardingApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

export async function completeCompanyOnboarding(input: unknown): Promise<CompanyOnboardingResponse> {
  const payload = companyOnboardingFormSchema.parse(input)

  // The Laravel backend (CompanySetupRequest) expects a nested payload keyed by
  // company / fiscal_year / accounts_setup / organization. Transform the flat
  // form values produced by react-hook-form + Zod into that shape. Sending the
  // flat payload directly results in a 422 because `company`, `fiscal_year`,
  // and `accounts_setup` are all marked `required|array` server-side.
  const body = {
    company: {
      name: payload.companyName,

      // Laravel treats empty string as invalid-but-nullable; send null instead.
      slug: payload.companySlug.trim() === '' ? null : payload.companySlug,
      timezone: payload.companyTimezone
    },
    fiscal_year: {
      code: payload.fiscalYearCode,
      name: payload.fiscalYearName,
      start_date: payload.fiscalYearStartDate,
      end_date: payload.fiscalYearEndDate
    },
    accounts_setup: {
      mode: payload.accountsSetupMode
    },
    organization: {
      create_root_org_unit: payload.createRootOrgUnit,
      root_org_unit_name: payload.rootOrgUnitName
    }
  }

  const res = await laravelBrowserClient.post('/api/v1/me/onboarding/company-setup', body)

  return companyOnboardingResponseSchema.parse(res.data)
}
