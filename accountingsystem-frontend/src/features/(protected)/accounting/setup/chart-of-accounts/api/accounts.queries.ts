import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  fetchAccounts,
  fetchAccountsTree,
  type AccountsQuery,
  createAccount,
  updateAccount,
  deleteAccount,
  fetchAccountStats,
  fetchAccountTypesByCoreType,
  fetchDetailTypesByAccountType
} from './accounts.api'

import type {
  AccountStatsResponse,
  AccountUpsertPayload
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

export function useAccountsQuery(query: AccountsQuery) {
  return useQuery({
    queryKey: ['accounts', query],
    queryFn: () => fetchAccounts(query),
    placeholderData: prev => prev, // keep old rows while new page loads
    staleTime: 10_000
  })
}

export function useAccountsTreeQuery(
  query: Omit<AccountsQuery, 'page' | 'pageSize' | 'sortField' | 'sortDir'> = {},
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: ['accounts', 'tree', query],
    queryFn: () => fetchAccountsTree(query),
    enabled: options?.enabled ?? true,
    staleTime: 10_000
  })
}

export function useCreateAccountMutation() {
  /**
   * Query for reads
   * The `useQueryClient` hook returns a client object that you can use to interact with the query cache.
   * This client object provides methods for reading and updating data in the cache.
   */

  const qc = useQueryClient()

  /**
   * Mutation for writes
   * Returns mutation object that contains the state of the mutation, such as whether it is currently in "idle", "loading", "error", or "success" states.
   *
   * In this case, the `mutationFn` option is set to `createAccount`, which is the function that will be called when the mutation is initiated.
   * The `onSuccess` option is set to a function that will be called when the mutation is successful.
   * This function invalidates the query for the 'accounts' endpoint, which will cause the component to re-fetch the data from the server when it is re-rendered.
   */
  return useMutation({
    mutationFn: createAccount,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['accounts'] })
  })
}

export function useUpdateAccountMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: AccountUpsertPayload }) => updateAccount(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['accounts'] })
  })
}

export function useDeleteAccountMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => deleteAccount(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['accounts'] })
  })
}

export function useAccountStatsQuery() {
  return useQuery<AccountStatsResponse>({
    queryKey: ['accounts', 'stats'],
    queryFn: fetchAccountStats
  })
}

export function useAccountTypesByCoreTypeQuery(coreType?: string) {
  return useQuery({
    queryKey: ['account-types', coreType],
    queryFn: () => fetchAccountTypesByCoreType(coreType!),
    enabled: !!coreType
  })
}

export function useDetailTypesByAccountTypeQuery(accountTypeId?: number) {
  return useQuery({
    queryKey: ['detail-types', accountTypeId],
    queryFn: () => fetchDetailTypesByAccountType(accountTypeId as number),
    enabled: Number.isInteger(accountTypeId) && (accountTypeId as number) > 0
  })
}
