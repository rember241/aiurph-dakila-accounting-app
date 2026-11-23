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

export class CompanySetupApiError extends Error {
  readonly status: number
  readonly fieldErrors: CompanyOnboardingApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: CompanyOnboardingApiFieldErrors | null = null) {
    super(message)
    this.name = 'CompanySetupApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

export async function completeTenantCompanySetup(input: unknown): Promise<CompanyOnboardingResponse> {
  const payload = companyOnboardingFormSchema.parse(input)

  const res = await laravelBrowserClient.post('/api/v1/tenants/company-setup', payload)

  return companyOnboardingResponseSchema.parse(res.data)
}
