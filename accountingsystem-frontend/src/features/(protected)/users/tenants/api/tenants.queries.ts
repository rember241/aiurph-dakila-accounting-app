import { type QueryClient, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { createTenant, fetchTenants } from './tenants.api'

import type { TenantQuery } from '@/features/(protected)/users/tenants/model/tenants.types'

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

export function useTenantsQuery(query: TenantQuery, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['tenants', query],
    queryFn: () => fetchTenants(query),
    enabled: options?.enabled ?? true,
    placeholderData: prev => prev,
    staleTime: 10_000
  })
}

export function useCreateTenantMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: createTenant,
    onSuccess: () => {
      clearTenantScopedQueries(qc)
      qc.invalidateQueries({ queryKey: ['tenants'] })
      qc.invalidateQueries({ queryKey: ['tenants', 'current'] })
      qc.invalidateQueries({ queryKey: ['auth', 'me'] })
      qc.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
      qc.invalidateQueries({ queryKey: ['period-management'] })
    }
  })
}
