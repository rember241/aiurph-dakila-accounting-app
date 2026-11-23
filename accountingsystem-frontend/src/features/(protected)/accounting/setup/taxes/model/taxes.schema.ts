import { z } from 'zod'

// Enum
const TAX_STATUS = ['active', 'inactive']

// Schema for creating a new tax
export const CreateTaxSchema = z.object({
  name: z.string().min(1, { error: 'Required' }),
  rate: z.coerce
    .number()
    .min(0.01, { error: 'Rate must be a minimum of 0.01' })
    .max(100.0, { error: 'Rate must not exceed 100' }),
  status: z.enum(TAX_STATUS)
})
