import { companyOnboardingSteps } from '@/features/onboarding/model/company-onboarding.milestones'

export type CreateCompanyWizardStep = {
  id: string
  title: string
  description: string
  fields?: (typeof companyOnboardingSteps)[number]['fields']
}

export const createCompanyWizardSteps: CreateCompanyWizardStep[] = companyOnboardingSteps.map(step => ({
  id: step.id,
  title: step.title,
  description: step.description,
  fields: step.fields
}))

export function findCreateCompanyStepIndexByField(
  fieldName: NonNullable<(typeof createCompanyWizardSteps)[number]['fields']>[number]
) {
  return createCompanyWizardSteps.findIndex(step => step.fields?.includes(fieldName))
}
