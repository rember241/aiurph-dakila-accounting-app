import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createOrgUnit,
  deleteOrgUnit,
  fetchCompanyStructure,
  updateOrgUnit,
  updateOrgUnitStatus
} from '@/features/(protected)/users/company-structure/api/company-structure.api'

import type {
  CompanyStructureResponse,
  OrgUnitFormValues,
  OrgUnitMutationResponse,
  OrgUnitStatusUpdateInput
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

export function useCompanyStructureQuery(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['company-structure'],
    queryFn: fetchCompanyStructure,
    enabled: options?.enabled ?? true,
    staleTime: 10_000
  })
}

export function useCreateOrgUnitMutation() {
  const queryClient = useQueryClient()

  return useMutation<OrgUnitMutationResponse, Error, OrgUnitFormValues>({
    mutationFn: createOrgUnit,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
    }
  })
}

export function useUpdateOrgUnitMutation() {
  const queryClient = useQueryClient()

  return useMutation<OrgUnitMutationResponse, Error, { id: string; values: OrgUnitFormValues }>({
    mutationFn: ({ id, values }) => updateOrgUnit(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
    }
  })
}

export function useUpdateOrgUnitStatusMutation() {
  const queryClient = useQueryClient()

  return useMutation<OrgUnitMutationResponse, Error, { id: string; values: OrgUnitStatusUpdateInput }>({
    mutationFn: ({ id, values }) => updateOrgUnitStatus(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['roles'] })
    }
  })
}

export function useDeleteOrgUnitMutation() {
  const queryClient = useQueryClient()

  return useMutation<{ message: string }, Error, string>({
    mutationFn: deleteOrgUnit,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['roles', 'stats'] })
    }
  })
}

export function getCompanyStructureInitialData(): CompanyStructureResponse {
  return {
    data: {
      units: [],
      hierarchy: [],
      stats: {
        total: 0,
        active: 0,
        inactive: 0,
        headOffice: 0,
        branches: 0,
        departments: 0,
        teams: 0
      }
    }
  }
}
