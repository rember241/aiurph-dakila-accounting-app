import {
  getCompanyStructureInitialData,
  useCompanyStructureQuery,
  useCreateOrgUnitMutation,
  useDeleteOrgUnitMutation,
  useUpdateOrgUnitMutation,
  useUpdateOrgUnitStatusMutation
} from '@/features/(protected)/users/company-structure/api/company-structure.queries'

import type {
  OrgUnitFormValues,
  OrgUnitStatusUpdateInput
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

/**
 * Thin controller hook for the company-structure workspace.
 *
 * It keeps the page component focused on layout while still exposing safe
 * fallback data during the first load.
 */
export function useCompanyStructureController() {
  const query = useCompanyStructureQuery()
  const createMutation = useCreateOrgUnitMutation()
  const updateMutation = useUpdateOrgUnitMutation()
  const statusMutation = useUpdateOrgUnitStatusMutation()
  const deleteMutation = useDeleteOrgUnitMutation()

  const data = query.data ?? getCompanyStructureInitialData()

  return {
    units: data.data.units,
    hierarchy: data.data.hierarchy,
    stats: data.data.stats,
    error: query.error,
    isBootstrapping: query.isLoading,
    isRefreshing: query.isFetching && !query.isLoading,
    isSaving:
      createMutation.isPending || updateMutation.isPending || statusMutation.isPending || deleteMutation.isPending,
    createOrgUnit: (values: OrgUnitFormValues) => createMutation.mutateAsync(values),
    updateOrgUnit: (id: string, values: OrgUnitFormValues) => updateMutation.mutateAsync({ id, values }),
    updateOrgUnitStatus: (id: string, values: OrgUnitStatusUpdateInput) => statusMutation.mutateAsync({ id, values }),
    deleteOrgUnit: (id: string) => deleteMutation.mutateAsync(id)
  }
}
