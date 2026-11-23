import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { createRole, deleteRole, fetchRoles, fetchRoleStats, transitionRoleStatus, updateRole } from './roles.api'

import type { RoleQuery, RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'

export function useRolesQuery(query: RoleQuery, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['roles', query],
    queryFn: () => fetchRoles(query),
    enabled: options?.enabled ?? true,
    placeholderData: previous => previous,
    staleTime: 10_000
  })
}

export function useRoleStatsQuery() {
  return useQuery({
    queryKey: ['roles', 'stats'],
    queryFn: fetchRoleStats,
    staleTime: 10_000
  })
}

export function useCreateRoleMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createRole,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['roles', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

export function useUpdateRoleMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ role, values }: { role: RoleRecord; values: unknown }) => updateRole(role, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['roles', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

export function useTransitionRoleStatusMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ roleId, status }: { roleId: string; status: RoleRecord['status'] }) =>
      transitionRoleStatus(roleId, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['roles', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

export function useDeleteRoleMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteRole,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['roles', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['company-structure'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}
