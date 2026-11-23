import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { ModifiedTax, TaxesQuery } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import {
  createTax,
  deleteTax,
  editTax,
  fetchActiveTaxes,
  fetchTaxes
} from '@/features/(protected)/accounting/setup/taxes/api/taxes.api'

/**
 * Custom hook to fetch taxes based on the
 * provided search/sort/pagination parameters
 *
 * @param {TaxesQuery} query The search/sort/pagination parameters
 */
export function useTaxesQuery(query: TaxesQuery) {
  return useQuery({
    queryKey: ['taxes', query],
    queryFn: () => fetchTaxes(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000
  })
}

/**
 * Custom hook to create a new tax based on the given data
 */
export function useCreateTaxMutation() {
  // Used to invalidate cached queries, forces refresh of taxes table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createTax,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['taxes'] })
  })
}

/**
 * Custom hook to edit a tax
 */
export function useEditTaxMutation() {
  // Used to invalidate cached queries, forces refresh of taxes table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (modifiedTax: ModifiedTax) => editTax(modifiedTax),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['taxes'] })
  })
}

/**
 * Custom hook to delete a tax
 */
export function useDeleteTaxMutation() {
  // Used to invalidate cached queries, forces refresh of taxes table
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteTax,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['taxes'] })
  })
}

export function useActiveTaxesQuery() {
  return useQuery({
    queryKey: ['taxes', 'active-options'],
    queryFn: fetchActiveTaxes,
    staleTime: 10_000
  })
}
