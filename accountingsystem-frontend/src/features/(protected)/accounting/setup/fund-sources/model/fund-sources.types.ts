import type { z } from 'zod'

import type { CreateFundSourceSchema } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.schema'

// Enums
export type FundSourceStatus = 'active' | 'inactive'

/*
 +----------------------------------------+
 |  DATA TYPES FOR FETCHING FUND SOURCES  |
 +----------------------------------------+
 */

// Query parameters for taxes
export type FundSourcesQuery = {
  sort?: string
  order?: string
  search?: string
  status?: FundSourceStatus | ''
  perPage?: number
  currentPage?: number
  isPaginated: boolean
}

// Data for fund sources columns
export type FundSourceColumn = {
  id: string
  name: string
  isUsed: boolean
  status: FundSourceStatus
}

// Mirrors the shape of the JSON response when fetching paginated fund source data
export type Paginated<T> = {
  data: T[]
  meta: {
    total: number
    per_page: number
    current_page: number
  }
}

/*
 +----------------------------------------+
 |  DATA TYPES FOR CREATING FUND SOURCES  |
 +----------------------------------------+
 */

// Type used for submitting modified fund source data
export type ModifiedFundSource = {
  id: string
  data: FundSourceSubmitValues
}

// Type of the entered data when creating a fund source
export type FundSourceFormValues = z.input<typeof CreateFundSourceSchema>

// Type validation when submitting the newly created fund source
export type FundSourceSubmitValues = z.output<typeof CreateFundSourceSchema>
