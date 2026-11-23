import { z } from 'zod'

// Enum
const FUND_SOURCE_STATUS = ['active', 'inactive']

// Schema for creating a new fund source
export const CreateFundSourceSchema = z.object({
  name: z.string().min(1, { error: 'Required' }),
  status: z.enum(FUND_SOURCE_STATUS)
})
