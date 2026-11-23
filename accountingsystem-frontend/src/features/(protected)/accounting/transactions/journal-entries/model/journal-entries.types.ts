import type { z } from 'zod'

import type { NormalBalance } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

import type {
  CreateJournalEntrySchema,
  EditJournalEntrySchema
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.schema'

// Enums
export type JournalEntryType = 'general' | 'adjusting' | 'closing'
export type JournalEntryStatus = 'posted' | 'draft' | 'void'

/*
 +-------------------------------------------+
 |  DATA TYPES FOR FETCHING JOURNAL ENTRIES  |
 +-------------------------------------------+
 */

// Query parameters for journal entries
export type JournalEntriesQuery = {
  sort?: string
  type?: string
  order?: string
  search?: string
  status?: string
  perPage?: number
  currentPage?: number
}

// Data type for journal items inside a single journal entry
export type JournalItem = {
  accountCode: string
  accountName: string
  taxName?: string | null
  taxRate?: number | null
  description?: string | null
  type: NormalBalance
  amount: number
}

// Data for journal entry columns
export type JournalEntryColumn = {
  id: string
  date: string
  type: string
  amount: number
  status: string
  fundSource: string
  note?: string | null
  journalNumber: number
  prefix: string
  referenceNumber: string
  journalItems: JournalItem[]
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
 |  DATA TYPES FOR CREATING/EDITING JOURNAL ENTRIES  |
 +---------------------------------------------------+
 */

// Types for data entered/submitted when creating a journal entry
export type CreateEntryFormValues = z.input<typeof CreateJournalEntrySchema>
export type CreateEntrySubmitValues = z.output<typeof CreateJournalEntrySchema>

// Types for data entered/submitted when editing a journal entry
export type EditEntryFormValues = z.input<typeof EditJournalEntrySchema>
export type EditEntrySubmitValues = z.output<typeof EditJournalEntrySchema>

// Wrapper type used for submitting modified journal entry data
export type ModifiedJournalEntry = {
  id: string
  data: EditEntrySubmitValues
}
