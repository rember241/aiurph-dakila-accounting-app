import { z } from 'zod'

export const navSearchSectionSchema = z.enum(['Dashboard', 'Sales', 'Inventory', 'Accounting', 'Users'])

export const navSearchEntrySchema = z.object({
  id: z.string(),
  label: z.string(),
  description: z.string(),
  href: z.string(),
  icon: z.string(),
  section: navSearchSectionSchema,
  keywords: z.array(z.string()),
  requiredAbilities: z.array(z.string())
})

export const navSearchResponseSchema = z.object({
  results: z.array(navSearchEntrySchema),
  query: z.string()
})
