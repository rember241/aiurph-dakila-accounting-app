import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type {
  JournalNumbersQuery,
  ModifiedJournalNumber
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'
import {
  createJournalNumber,
  deleteJournalNumber,
  editJournalNumber,
  fetchJournalNumbers
} from '@/features/(protected)/accounting/setup/transaction-series/api/transaction-series.api'

/**
 * Loads transaction-series records for the setup screen.
 */
export function useJournalNumbersQuery(query: JournalNumbersQuery) {
  return useQuery({
    queryKey: ['journalNumbers', query],
    queryFn: () => fetchJournalNumbers(query),

    // Keep the previous page visible while the next query is loading so table
    // pagination and filtering feel stable.
    placeholderData: previousData => previousData,
    staleTime: 10_000
  })
}

/**
 * Creates a transaction series and refreshes the cached list.
 */
export function useCreateJournalNumberMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createJournalNumber,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalNumbers'] })
  })
}

/**
 * Updates a transaction series entry.
 */
export function useEditJournalNumberMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (modifiedJournalNumber: ModifiedJournalNumber) => editJournalNumber(modifiedJournalNumber),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalNumbers'] })
  })
}

/**
 * Deletes a transaction series entry and refreshes the table.
 */
export function useDeleteJournalNumberMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteJournalNumber,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['journalNumbers'] })
  })
}
