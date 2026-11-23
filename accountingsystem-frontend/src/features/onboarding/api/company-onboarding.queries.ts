import { type QueryClient, useMutation, useQueryClient } from '@tanstack/react-query'

import { completeCompanyOnboarding } from './company-onboarding.api'
import { createCompletedOnboardingStatus } from '@/features/auth/model/auth.schema'

import type { MeResponse } from '@/features/auth/model/auth.schema'

function clearTenantScopedQueries(queryClient: QueryClient) {
  queryClient.removeQueries({ queryKey: ['period-management'] })
  queryClient.removeQueries({ queryKey: ['accounts'] })
  queryClient.removeQueries({ queryKey: ['journalEntries'] })
  queryClient.removeQueries({ queryKey: ['inventory-items'] })
  queryClient.removeQueries({ queryKey: ['customers'] })
  queryClient.removeQueries({ queryKey: ['sales-invoices'] })
  queryClient.removeQueries({ queryKey: ['roles'] })
  queryClient.removeQueries({ queryKey: ['users'] })
}

export function useCompleteCompanyOnboardingMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: completeCompanyOnboarding,
    onSuccess: () => {
      clearTenantScopedQueries(qc)
      qc.setQueryData<MeResponse>(['auth', 'me'], previous => {
        if (!previous?.user) {
          return previous
        }

        return {
          ...previous,
          user: {
            ...previous.user,
            onboarding: createCompletedOnboardingStatus()
          }
        }
      })
      qc.invalidateQueries({ queryKey: ['auth', 'me'] })
      qc.invalidateQueries({ queryKey: ['tenants'] })
      qc.invalidateQueries({ queryKey: ['tenants', 'current'] })
      qc.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'fiscal-years'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'periods'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'kpi'] })
    }
  })
}
