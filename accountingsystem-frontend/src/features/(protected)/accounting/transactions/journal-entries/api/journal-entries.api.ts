import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  JournalEntriesQuery,
  JournalEntryColumn,
  CreateEntrySubmitValues,
  ModifiedJournalEntry,
  Paginated,
  EditEntrySubmitValues
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'

/**
 * Retrieves all journal entry data from the backend server
 *
 * @param {JournalEntriesQuery} query
 * @return {Promise<Paginated<JournalEntryColumn>>}
 */
export async function fetchJournalEntries(query: JournalEntriesQuery): Promise<Paginated<JournalEntryColumn>> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/journal-entries?${queryString}`)

  return response.data as Paginated<JournalEntryColumn>
}

/**
 * Retrieves a single journal entry for editing
 *
 * @param {string} journalEntryId - The ID of the journal entry to be retrieved
 */
export async function fetchEntryForEdit(journalEntryId: string) {
  const response = await laravelBrowserClient.get(`/api/v1/journal-entries/${journalEntryId}/edit`)

  return response.data as EditEntrySubmitValues
}

/**
 * Creates a new journal entry
 *
 * @param {CreateEntrySubmitValues} newJournalEntry - The validated data entered from the Journal Entry form
 */
export async function createJournalEntry(newJournalEntry: CreateEntrySubmitValues) {
  const response = await laravelBrowserClient.post('/api/v1/journal-entries', newJournalEntry)

  return response.data
}

/**
 * Updates the data of a journal entry that was saved as a draft
 *
 * @param id The ID of the journal entry to modify
 * @param modifiedEntryData Contains the modified data for this journal entry
 */
export async function editJournalEntry({ id, data }: ModifiedJournalEntry) {
  const response = await laravelBrowserClient.patch(`/api/v1/journal-entries/${id}`, data)

  return response.data
}

/**
 * Posts an entry that was saved as d draft
 *
 * @param journalEntryId - The ID of the journal entry to post
 */
export async function postJournalEntry(journalEntryId: string) {
  const response = await laravelBrowserClient.patch(`/api/v1/journal-entries/${journalEntryId}/post`)

  return response.data
}

/**
 * Voids a journal entry that was saved as a draft or already posted
 *
 * @param journalEntryId - The ID of the journal entry to void
 */
export async function voidJournalEntry(journalEntryId: string) {
  const response = await laravelBrowserClient.patch(`/api/v1/journal-entries/${journalEntryId}/void`)

  return response.data
}

/**
 * Converts a journal query object into a URL-encoded query string.
 * Empty or undefined properties in the query object are excluded
 * from the resulting string.
 *
 * @param {JournalEntriesQuery} query
 * @returns {string}
 */
function toQueryString(query: JournalEntriesQuery): string {
  const queryParameters = new URLSearchParams()

  if (query.sort) queryParameters.set('sort', query.sort)
  if (query.type) queryParameters.set('type', query.type)
  if (query.order) queryParameters.set('order', query.order)
  if (query.search) queryParameters.set('search', query.search)
  if (query.status) queryParameters.set('status', query.status)
  if (query.perPage) queryParameters.set('per_page', String(query.perPage))
  if (query.currentPage) queryParameters.set('page', String(query.currentPage))

  return queryParameters.toString()
}
