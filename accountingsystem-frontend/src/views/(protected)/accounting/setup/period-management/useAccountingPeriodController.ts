import {
  useCreateFiscalYearMutation,
  useDeleteFiscalYearMutation,
  useGetCurrentFiscalYearQuery,
  useSetCurrentFiscalYearMutation,
  useUpdateFiscalYearMutation,
  useTransitionPeriodStatusMutation
} from '@/features/(protected)/accounting/setup/period-management/api/period-management.queries'

export function useAccountingPeriodController() {
  const createYear = useCreateFiscalYearMutation()
  const setCurrentYear = useSetCurrentFiscalYearMutation()
  const getCurrentYear = useGetCurrentFiscalYearQuery()
  const setPeriodStatus = useTransitionPeriodStatusMutation()
  const updateFiscalYear = useUpdateFiscalYearMutation()
  const deleteFiscalYear = useDeleteFiscalYearMutation()

  return { createYear, setCurrentYear, getCurrentYear, setPeriodStatus, updateFiscalYear, deleteFiscalYear }
}
