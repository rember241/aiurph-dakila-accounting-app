import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { fetchAccountingDefaults, updateAccountingDefaults } from './accounting-defaults.api'
import type { UpdateAccountingDefaultsPayload } from '../model/accounting-defaults.types'

export const ACCOUNTING_DEFAULTS_KEY = ['accounting-defaults'] as const

export function useAccountingDefaultsQuery() {
  return useQuery({
    queryKey: ACCOUNTING_DEFAULTS_KEY,
    queryFn: fetchAccountingDefaults,
    staleTime: 30_000
  })
}

export function useUpdateAccountingDefaultsMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (payload: UpdateAccountingDefaultsPayload) => updateAccountingDefaults(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ACCOUNTING_DEFAULTS_KEY })
  })
}
