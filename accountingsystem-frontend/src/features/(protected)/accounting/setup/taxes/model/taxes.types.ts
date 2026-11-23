import type { z } from 'zod'

import type { CreateTaxSchema } from '@/features/(protected)/accounting/setup/taxes/model/taxes.schema'

// Enums
export type TaxStatus = 'active' | 'inactive'

/*
 +---------------------------------+
 |  DATA TYPES FOR FETCHING TAXES  |
 +---------------------------------+
 */

// Query parameters for taxes
export type TaxesQuery = {
  sort?: string
  order?: string
  search?: string
  status?: TaxStatus | ''
  perPage?: number
  isPaginated: boolean
  currentPage?: number
}

// Data for tax columns
export type TaxColumn = {
  id: string
  name: string
  rate: number
  isUsed: boolean
  status: TaxStatus
}

// Mirrors the shape of the JSON response when fetching paginated tax data
export type Paginated<T> = {
  data: T[]
  meta: {
    total: number
    per_page: number
    current_page: number
  }
}

/*
 +-----------------------------------------+
 |  DATA TYPES FOR CREATING/EDITING TAXES  |
 +-----------------------------------------+
 */

// Type used for submitting modified tax data
export type ModifiedTax = {
  id: string
  data: TaxSubmitValues
}

// Type of the entered data when creating a new tax
export type TaxFormValues = z.input<typeof CreateTaxSchema>

// Type validation when submitting the newly created tax
export type TaxSubmitValues = z.output<typeof CreateTaxSchema>
