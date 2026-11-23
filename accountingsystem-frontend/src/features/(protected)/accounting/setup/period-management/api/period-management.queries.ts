import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createFiscalYear,
  deleteFiscalYear,
  fetchPeriodManagementKpi,
  fetchAccountingPeriods,
  fetchFiscalYears,
  getCurrentFiscalYear,
  setCurrentFiscalYear,
  updateFiscalYear,
  transitionAccountingPeriodStatus
} from './period-management.api'

import type {
  AccountingPeriodsQuery,
  FiscalYearsQuery,
  UpdateFiscalYearPayload,
  TransitionPeriodStatusPayload
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

// Fiscal Year Mutations
export function useFiscalYearsQuery(query: FiscalYearsQuery) {
  return useQuery({
    queryKey: ['period-management', 'fiscal-years', query],
    queryFn: () => fetchFiscalYears(query),
    placeholderData: prev => prev,
    staleTime: 10_000
  })
}

export function useGetCurrentFiscalYearQuery() {
  return useQuery({
    queryKey: ['period-management', 'fiscal-years', 'current'],
    queryFn: () => getCurrentFiscalYear(),
    placeholderData: prev => prev,
    staleTime: 10_000
  })
}

export function usePeriodManagementKpiQuery() {
  return useQuery({
    queryKey: ['period-management', 'kpi'],
    queryFn: () => fetchPeriodManagementKpi(),
    placeholderData: prev => prev,
    staleTime: 10_000
  })
}

export function useCreateFiscalYearMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: createFiscalYear,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['period-management', 'fiscal-years'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'periods'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'kpi'] })
    }
  })
}

export function useSetCurrentFiscalYearMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => setCurrentFiscalYear(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['period-management', 'fiscal-years'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'periods'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'kpi'] })
    }
  })
}

export function useUpdateFiscalYearMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateFiscalYearPayload }) => updateFiscalYear(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['period-management', 'fiscal-years'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'periods'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'kpi'] })
    }
  })
}

export function useDeleteFiscalYearMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => deleteFiscalYear(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['period-management', 'fiscal-years'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'periods'] })
      qc.invalidateQueries({ queryKey: ['period-management', 'kpi'] })
    }
  })
}

// Per Period Mutations

export function useAccountingPeriodsQuery<T>(query: AccountingPeriodsQuery, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['period-management', 'periods', query],
    queryFn: () => fetchAccountingPeriods(query),
    enabled: options?.enabled ?? true,
    select: (response: any) => {
      if (response && 'data' in response && Array.isArray(response.data)) {
        return (query.isPaginated === false ? response.data : response) as T
      }

      return response as T
    },
    placeholderData: prev => prev,
    staleTime: 10_000
  })
}

export function useTransitionPeriodStatusMutation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: TransitionPeriodStatusPayload }) =>
      transitionAccountingPeriodStatus(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['period-management'] })
  })
}
