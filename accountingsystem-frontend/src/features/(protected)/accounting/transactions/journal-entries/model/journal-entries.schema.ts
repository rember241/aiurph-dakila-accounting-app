import { z } from 'zod'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

// Enums
const JOURNAL_TYPES = ['general', 'adjusting', 'closing']
const JOURNAL_STATUSES = ['posted', 'draft']

// Schema for nested journal items when creating new journal entries
const JournalItemSchema = z.object({
  accountId: z.string().min(1, { error: 'Required' }),
  description: z.string().max(250).optional(),
  taxId: z.string().optional(),
  debit: z.coerce.number().optional().default(0),
  credit: z.coerce.number().optional().default(0)
})

// Schema for creating a new journal entry
export const CreateJournalEntrySchema = z.object({
  journalNumberId: z.string().min(1, { error: 'Required' }),
  date: z.instanceof(dayjs as any, { error: 'Required' }).transform(val => (val as Dayjs).format('YYYY-MM-DD')),
  type: z.enum(JOURNAL_TYPES, { error: 'Required' }).default('general'),
  fundSourceId: z.string().min(1, { error: 'Required' }),
  referenceNumber: z.string().optional(),
  note: z.string().max(500).optional(),
  status: z.enum(JOURNAL_STATUSES),
  journalItems: z.array(JournalItemSchema).min(2)
})

// Schema for editing a journal entry
export const EditJournalEntrySchema = CreateJournalEntrySchema.omit({ journalNumberId: true }).extend({
  prefix: z.string(),
  journalNumber: z.number()
})
