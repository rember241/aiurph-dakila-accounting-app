import { type QueryClient, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { fetchCurrentTenant, updateCurrentTenant } from './current-tenant.api'

import type { CurrentTenantState } from '@/features/(protected)/users/tenants/model/current-tenant.types'

function clearTenantScopedQueries(queryClient: QueryClient) {
  queryClient.removeQueries({ queryKey: ['period-management'] })
  queryClient.removeQueries({ queryKey: ['accounts'] })
  queryClient.removeQueries({ queryKey: ['journalEntries'] })
  queryClient.removeQueries({ queryKey: ['inventory-items'] })
  queryClient.removeQueries({ queryKey: ['customers'] })
  queryClient.removeQueries({ queryKey: ['sales-invoices'] })
  queryClient.removeQueries({ queryKey: ['roles'] })
  queryClient.removeQueries({ queryKey: ['users'] })
  queryClient.removeQueries({ queryKey: ['auth', 'tenant-access'] })
}

export function useCurrentTenantQuery() {
  return useQuery({
    queryKey: ['tenants', 'current'],
    queryFn: fetchCurrentTenant,

    // Mirror the tenant-access query: 5-minute staleTime + no window-focus
    // refetch so /api/v1/company is not polled every time focus returns.
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    retry: false
  })
}

export function useSwitchCurrentTenantMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: updateCurrentTenant,
    onSuccess: data => {
      clearTenantScopedQueries(qc)
      qc.setQueryData<CurrentTenantState>(['tenants', 'current'], data)
      qc.invalidateQueries({ queryKey: ['tenants', 'current'] })
      qc.invalidateQueries({ queryKey: ['tenants'] })
      qc.invalidateQueries({ queryKey: ['auth', 'me'] })
      qc.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
      qc.invalidateQueries({ queryKey: ['period-management'] })
    }
  })
}
