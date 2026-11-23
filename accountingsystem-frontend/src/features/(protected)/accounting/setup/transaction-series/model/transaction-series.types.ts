import type { z } from 'zod'

import type { CreateJournalNumberSchema } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.schema'

/**
 * This module still carries the older "journal number" naming in code.
 *
 * The screen is effectively the shared transaction-series setup area. The
 * legacy names are left in place for stability until the team decides to do a
 * dedicated rename pass.
 */

// Enums
export type JournalNumberStatus = 'active' | 'inactive'
export type JournalNumberResetInterval = 'daily' | 'weekly' | 'monthly' | 'yearly'

/*
 +-------------------------------------------+
 |  DATA TYPES FOR FETCHING JOURNAL NUMBERS  |
 +-------------------------------------------+
 */

// Query parameters for journal numbers
export type JournalNumbersQuery = {
  search?: string
  status?: string
  resetInterval?: string
  sort?: string
  order?: string
  perPage?: number
  currentPage?: number
  isPaginated?: boolean
}

// Data for journal number column
export type JournalNumberColumn = {
  id: string
  name: string
  prefix: string
  isUsed: boolean
  startingNumber: number
  status: JournalNumberStatus
  resetInterval: JournalNumberResetInterval
}

// Mirrors the shape of the JSON response when fetching paginated journal entry data
export type Paginated<T> = {
  data: T[]
  meta: {
    total: number
    per_page: number
    current_page: number
  }
}

/*
 +---------------------------------------------------+
 |  DATA TYPES FOR CREATING/EDITING JOURNAL NUMBERS  |
 +---------------------------------------------------+
 */

// Types for data entered/submitted when creating/editing a journal number
export type JournalNumberFormValues = z.input<typeof CreateJournalNumberSchema>
export type JournalNumberSubmitValues = z.output<typeof CreateJournalNumberSchema>

// Wrapper type used for submitting modified journal number data
export type ModifiedJournalNumber = {
  id: string
  data: JournalNumberSubmitValues
}
