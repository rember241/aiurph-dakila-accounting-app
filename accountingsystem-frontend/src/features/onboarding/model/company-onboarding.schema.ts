import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import { isValidTimeZone, tenantRecordSchema } from '@/features/(protected)/users/tenants/model/tenants.schema'
import {
  orgUnitStatusSchema,
  orgUnitTypeSchema
} from '@/features/(protected)/users/company-structure/model/company-structure.schema'

const tenantSlugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

const fiscalYearSchema = z.object({
  id: zUuid(),
  code: z.string().min(1),
  name: z.string().min(1),
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  status: z.enum(['open', 'closed']),
  is_current: z.boolean(),
  closed_at: z.string().nullable(),
  periods_count: z.number().int().optional(),
  closed_periods_count: z.number().int().optional(),
  periods: z.array(z.unknown()).optional(),
  created_at: z.string(),
  updated_at: z.string()
})

const onboardingRootOrgUnitSchema = z.object({
  id: zUuid(),
  company_id: zUuid(),
  tenant_id: zUuid(),
  parent_org_unit_id: zUuid().nullable(),
  type: orgUnitTypeSchema,
  name: z.string().min(1),
  code: z.string().min(1),
  status: orgUnitStatusSchema,
  is_root: z.boolean(),
  created_at: z.string(),
  updated_at: z.string()
})

export const companyOnboardingFormSchema = z
  .object({
    companyName: z.string().trim().min(2, 'Company name is required.').max(160, 'Maximum 160 characters.'),
    companySlug: z
      .string()
      .trim()
      .max(160, 'Maximum 160 characters.')
      .refine(
        value => value === '' || tenantSlugPattern.test(value),
        'Use lowercase letters, numbers, and hyphens only.'
      ),
    companyTimezone: z
      .string()
      .trim()
      .min(1, 'Timezone is required.')
      .refine(value => isValidTimeZone(value), 'Select a valid timezone.'),
    createRootOrgUnit: z.boolean(),
    rootOrgUnitName: z.string().trim().max(160, 'Maximum 160 characters.'),
    fiscalYearCode: z.string().trim().min(1, 'Fiscal year code is required.').max(50, 'Maximum 50 characters.'),
    fiscalYearName: z.string().trim().min(1, 'Fiscal year name is required.').max(100, 'Maximum 100 characters.'),
    fiscalYearStartDate: z.string().date('Start date must be a valid date.'),
    fiscalYearEndDate: z.string().date('End date must be a valid date.'),
    accountsSetupMode: z.enum(['generated', 'empty'])
  })
  .refine(
    data => {
      if (!data.fiscalYearStartDate) return true

      const start = new Date(`${data.fiscalYearStartDate}T00:00:00`)

      return start.getDate() === 1
    },
    {
      message: 'Start date must be the first day of the month.',
      path: ['fiscalYearStartDate']
    }
  )
  .refine(
    data => {
      if (!data.fiscalYearStartDate || !data.fiscalYearEndDate) return true

      const start = new Date(`${data.fiscalYearStartDate}T00:00:00`)
      const end = new Date(`${data.fiscalYearEndDate}T00:00:00`)

      if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return true
      }

      return end.getTime() >= start.getTime()
    },
    {
      message: 'End date must be after the start date.',
      path: ['fiscalYearEndDate']
    }
  )
  .superRefine((data, ctx) => {
    if (data.createRootOrgUnit && data.rootOrgUnitName.trim().length < 2) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['rootOrgUnitName'],
        message: 'Root org unit name is required.'
      })
    }
  })

export const companyOnboardingResponseSchema = z.object({
  data: z.object({
    tenant: tenantRecordSchema,
    organization: z.object({
      root_org_unit: onboardingRootOrgUnitSchema.nullable()
    }),
    fiscal_year: fiscalYearSchema,
    accounts_setup: z.object({
      mode: z.enum(['generated', 'empty']),
      created_count: z.number().int().nonnegative()
    })
  }),
  message: z.string()
})

export const companyOnboardingApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})
