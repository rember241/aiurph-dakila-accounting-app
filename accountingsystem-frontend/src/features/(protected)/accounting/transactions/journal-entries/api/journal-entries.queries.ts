import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type {
  JournalEntriesQuery,
  ModifiedJournalEntry
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import {
  createJournalEntry,
  editJournalEntry,
  fetchJournalEntries,
  fetchEntryForEdit,
  postJournalEntry,
  voidJournalEntry
} from '@/features/(protected)/accounting/transactions/journal-entries/api/journal-entries.api'

/**
 * Custom hook to fetch journal entries based on
 * provided search/sort/filter/pagination parameters
 *
 * @param {JournalEntriesQuery} query The search/sort/filter/pagination parameters
 */
export function useJournalEntriesQuery(query: JournalEntriesQuery) {
  return useQuery({
    queryKey: ['journalEntries', query],
    queryFn: () => fetchJournalEntries(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000
  })
}

/**
 * Custom hook to fetch a single journal entry for editing
 *
 * @param {string} journalEntryId - The ID of the journal entry to be retrieved
 */
export function useEditJournalEntryQuery(journalEntryId: string) {
  return useQuery({
    queryKey: ['journalEntries', journalEntryId],
    queryFn: () => fetchEntryForEdit(journalEntryId)
  })
}

/**
 * Custom hook to create a new journal entry based on the given data
 */
export function useCreateJournalEntryMutation() {
  // Used to invalidate cached queries, forces refresh of journal entries table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createJournalEntry,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
  })
}

/**
 * Custom hook to edit a journal entry that was saved as a draft
 */
export function useEditJournalEntryMutation() {
  // Used to invalidate cached queries, forces refresh of journal entries table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (modifiedEntry: ModifiedJournalEntry) => editJournalEntry(modifiedEntry),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
  })
}

/**
 * Custom hook to post a journal entry that was saved as draft
 */
export function usePostJournalEntryMutation() {
  // Used to invalidate cached queries, forces refresh of journal entries table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: postJournalEntry,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
  })
}

/**
 * Custom hook to void a journal entry that was saved as draft or already posted
 */
export function useVoidJournalEntryMutation() {
  // Used to invalidate cached queries, forces refresh of journal entries table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: voidJournalEntry,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
  })
}
