import { z } from 'zod'

export const FISCAL_YEAR_STATUS = ['open', 'closed'] as const
export const PERIOD_STATUS = ['open', 'soft_closed', 'closed'] as const

export const createFiscalYearSchema = z
  .object({
    code: z.string().min(1, 'Code is required').max(50, 'Max 50 characters'),
    name: z.string().min(1, 'Name is required').max(100, 'Max 100 characters'),
    start_date: z.string().date('Start date must be a valid date'),
    end_date: z.string().date('End date must be a valid date'),
    is_current: z.coerce.boolean().optional().default(false)
  })
  .refine(
    data => {
      if (!data.start_date) return true

      const start = new Date(`${data.start_date}T00:00:00`)

      return start.getDate() === 1
    },
    {
      message: 'Start date must be the first day of the month.',
      path: ['start_date']
    }
  )

export const transitionPeriodStatusSchema = z.object({
  status: z.enum(PERIOD_STATUS)
})

export const updateFiscalYearSchema = z.object({
  code: z.string().min(1, 'Code is required').max(20, 'Max 20 characters'),
  name: z.string().min(1, 'Name is required').max(50, 'Max 50 characters'),
  status: z.enum(FISCAL_YEAR_STATUS)
})

export type CreateFiscalYearFormValues = z.input<typeof createFiscalYearSchema>
export type CreateFiscalYearSubmitValues = z.output<typeof createFiscalYearSchema>

export type TransitionPeriodStatusFormValues = z.input<typeof transitionPeriodStatusSchema>
export type TransitionPeriodStatusSubmitValues = z.output<typeof transitionPeriodStatusSchema>

export type UpdateFiscalYearFormValues = z.input<typeof updateFiscalYearSchema>
export type UpdateFiscalYearSubmitValues = z.output<typeof updateFiscalYearSchema>
