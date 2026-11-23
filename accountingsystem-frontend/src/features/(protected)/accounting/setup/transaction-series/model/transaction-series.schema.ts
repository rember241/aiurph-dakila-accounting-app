import { z } from 'zod'

// Enum
const RESET_INTERVALS = ['daily', 'weekly', 'monthly', 'yearly']
const JOURNAL_NUMBER_STATUS = ['active', 'inactive']

/**
 * Shared validation for the transaction-series form.
 *
 * The backend currently treats this setup as a reusable sequence definition,
 * so the frontend only validates the fields common to every series record.
 */
export const CreateJournalNumberSchema = z.object({
  name: z.string().min(1, { error: 'Required' }),
  prefix: z.string().min(1, { error: 'Required' }),
  resetInterval: z.enum(RESET_INTERVALS),
  status: z.enum(JOURNAL_NUMBER_STATUS)
})
