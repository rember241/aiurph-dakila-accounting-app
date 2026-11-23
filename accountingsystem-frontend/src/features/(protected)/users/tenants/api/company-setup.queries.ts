import { type QueryClient, useMutation, useQueryClient } from '@tanstack/react-query'

import { completeTenantCompanySetup } from './company-setup.api'

function clearTenantScopedQueries(queryClient: QueryClient) {
  queryClient.removeQueries({ queryKey: ['period-management'] })
  queryClient.removeQueries({ queryKey: ['accounts'] })
  queryClient.removeQueries({ queryKey: ['journalEntries'] })
  queryClient.removeQueries({ queryKey: ['inventory-items'] })
  queryClient.removeQueries({ queryKey: ['customers'] })
  queryClient.removeQueries({ queryKey: ['sales-invoices'] })
  queryClient.removeQueries({ queryKey: ['roles'] })
}

export function useCompleteTenantCompanySetupMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: completeTenantCompanySetup,
    onSuccess: () => {
      clearTenantScopedQueries(qc)
      qc.invalidateQueries({ queryKey: ['tenants'] })
      qc.invalidateQueries({ queryKey: ['tenants', 'current'] })
      qc.invalidateQueries({ queryKey: ['auth', 'me'] })
      qc.invalidateQueries({ queryKey: ['period-management'] })
    }
  })
}
