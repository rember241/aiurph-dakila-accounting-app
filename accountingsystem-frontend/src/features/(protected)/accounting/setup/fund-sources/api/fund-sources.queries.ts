import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type {
  FundSourcesQuery,
  ModifiedFundSource
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'
import {
  createFundSource,
  deleteFundSource,
  editFundSource,
  fetchFundSources
} from '@/features/(protected)/accounting/setup/fund-sources/api/fund-sources.api'

/**
 * Custom hook to fetch fund sources based on the
 * provided search/sort/pagination parameters
 *
 * @param {FundSourcesQuery} query The search/sort/pagination parameters
 */
export function useFundSourcesQuery(query: FundSourcesQuery) {
  return useQuery({
    queryKey: ['fund-sources', query],
    queryFn: () => fetchFundSources(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000
  })
}

/**
 * Custom hook to create a new fund source based on the given data
 */
export function useCreateFundSourceMutation() {
  // Used to invalidate cached queries, forces refresh of fund sources table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createFundSource,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['fund-sources'] })
  })
}

/**
 * Custom hook to edit a fund source
 */
export function useEditFundSourceMutation() {
  // Used to invalidate cached queries, forces refresh of fund sources table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (modifiedFundSource: ModifiedFundSource) => editFundSource(modifiedFundSource),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['fund-sources'] })
  })
}

/**
 * Custom hook to delete a fund source
 */
export function useDeleteFundSourceMutation() {
  // Used to invalidate cached queries, forces refresh of fund sources table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteFundSource,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['fund-sources'] })
  })
}
