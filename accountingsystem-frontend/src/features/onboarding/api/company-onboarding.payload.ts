import type { CompanyOnboardingFormValues } from '@/features/onboarding/model/company-onboarding.types'

type CanonicalCompanyOnboardingPayload = {
  company: {
    name: string
    slug?: string
    timezone: string
  }
  organization?: {
    create_root_org_unit: boolean
    root_org_unit_name?: string
  }
  fiscal_year: {
    code: string
    name: string
    start_date: string
    end_date: string
  }
  accounts_setup: {
    mode: 'generated' | 'empty'
  }
}

export function buildCompanyOnboardingPayload(
  values: CompanyOnboardingFormValues,
  options: { includeOrganization: boolean }
): CanonicalCompanyOnboardingPayload {
  return {
    company: {
      name: values.companyName,
      slug: values.companySlug || undefined,
      timezone: values.companyTimezone
    },
    ...(options.includeOrganization
      ? {
          organization: {
            create_root_org_unit: values.createRootOrgUnit,
            root_org_unit_name: values.createRootOrgUnit ? values.rootOrgUnitName : undefined
          }
        }
      : {}),
    fiscal_year: {
      code: values.fiscalYearCode,
      name: values.fiscalYearName,
      start_date: values.fiscalYearStartDate,
      end_date: values.fiscalYearEndDate
    },
    accounts_setup: {
      mode: values.accountsSetupMode
    }
  }
}
