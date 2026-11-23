import type { TimelineDotProps } from '@mui/lab/TimelineDot'
import type { ChipProps } from '@mui/material/Chip'

import type { CompanyOnboardingFormValues } from './company-onboarding.types'

export type OnboardingMilestoneStatus = 'included' | 'automated' | 'planned'
export type OnboardingMilestoneKind = 'step' | 'future'

export type OnboardingMilestone = {
  id: string
  kind: OnboardingMilestoneKind
  phase: string
  title: string
  description: string
  iconClassName: string
  status: OnboardingMilestoneStatus
  tags: string[]
  fields?: Array<keyof CompanyOnboardingFormValues>
}

export const companyOnboardingMilestones: OnboardingMilestone[] = [
  {
    id: 'company-profile',
    kind: 'step',
    phase: 'Setup',
    title: 'Company profile',
    description: 'Add your company name, company URL, and timezone so your ERP is configured correctly.',
    iconClassName: 'bx bx-buildings',
    status: 'included',
    tags: ['Required now', 'Company identity', 'Timezone-aware'],
    fields: ['companyName', 'companySlug', 'companyTimezone']
  },
  {
    id: 'company-structure',
    kind: 'step',
    phase: 'Setup',
    title: 'Company structure',
    description: 'Set up the initial head office unit so your company structure starts with the right root node.',
    iconClassName: 'bx bx-sitemap',
    status: 'included',
    tags: ['Required now', 'Root org unit', 'Expandable later'],
    fields: ['createRootOrgUnit', 'rootOrgUnitName']
  },
  {
    id: 'initial-fiscal-year',
    kind: 'step',
    phase: 'Setup',
    title: 'Initial fiscal year',
    description: 'Set the first fiscal year so reporting, closing, and accounting periods are ready from day one.',
    iconClassName: 'bx bx-calendar-check',
    status: 'included',
    tags: ['Required now', 'Current year', 'Accounting ready'],
    fields: ['fiscalYearCode', 'fiscalYearName', 'fiscalYearStartDate', 'fiscalYearEndDate']
  },
  {
    id: 'chart-of-accounts',
    kind: 'step',
    phase: 'Setup',
    title: 'Chart of accounts',
    description: 'Review the starter chart of accounts that will be prepared for the company.',
    iconClassName: 'bx bx-book-content',
    status: 'included',
    tags: ['Included setup', 'Starter chart', 'Can change later'],
    fields: ['accountsSetupMode']
  },
  {
    id: 'review-and-launch',
    kind: 'step',
    phase: 'Setup',
    title: 'Review and launch',
    description: 'Review your company details, initial accounting setup, and launch the ERP when everything looks right.',
    iconClassName: 'bx bx-check-shield',
    status: 'automated',
    tags: ['Final review', 'Company ready', 'Access prepared']
  },
  {
    id: 'controls-and-tax',
    kind: 'future',
    phase: 'Next phase',
    title: 'Controls and compliance',
    description: 'Extend setup later with deeper org units, tax settings, approval policies, and access controls.',
    iconClassName: 'bx bx-check-shield',
    status: 'planned',
    tags: ['Scalable next', 'Tax setup', 'Approval controls']
  }
]

export const companyOnboardingSteps = companyOnboardingMilestones.filter(
  milestone => milestone.kind === 'step'
)

export function findCompanyOnboardingStepIndexByField(fieldName: keyof CompanyOnboardingFormValues) {
  return companyOnboardingSteps.findIndex(step => step.fields?.includes(fieldName))
}

export function getMilestoneTone(status: OnboardingMilestoneStatus): {
  chipColor: ChipProps['color']
  chipLabel: string
  dotColor: TimelineDotProps['color']
  dotVariant: 'filled' | 'outlined' | 'tonal'
} {
  switch (status) {
    case 'included':
      return {
        chipColor: 'primary',
        chipLabel: 'Included now',
        dotColor: 'primary',
        dotVariant: 'filled'
      }
    case 'automated':
      return {
        chipColor: 'success',
        chipLabel: 'Automatic',
        dotColor: 'success',
        dotVariant: 'tonal'
      }
    case 'planned':
      return {
        chipColor: 'secondary',
        chipLabel: 'Planned',
        dotColor: 'secondary',
        dotVariant: 'outlined'
      }
  }
}
