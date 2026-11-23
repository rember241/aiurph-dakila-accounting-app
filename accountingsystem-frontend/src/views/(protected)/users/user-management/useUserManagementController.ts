import { useCallback, useMemo } from 'react'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import { useCompanyStructureQuery } from '@/features/(protected)/users/company-structure/api/company-structure.queries'
import {
  useCreateUserMutation,
  useDeleteUserMutation,
  useTransitionUserStatusMutation,
  useUpdateUserMutation,
  useUsersQuery,
  useUserStatsQuery
} from '@/features/(protected)/users/user-management/api/user-management.queries'
import { useRolesQuery } from '@/features/(protected)/users/roles/api/roles.queries'
import {
  type UserOrgUnitOption,
  type UserRoleOption,
  type UserStatus
} from '@/features/(protected)/users/user-management/model/user-management.types'
import type { UserFormSubmitValues } from '@/features/(protected)/users/user-management/model/user-management.schema'

type UseUserManagementControllerOptions = {
  canReadOrgUnits?: boolean
  canReadRoles?: boolean
}

/**
 * Consolidates the user-management page queries and mutations into a single
 * controller hook.
 *
 * The page stays mostly presentation-focused while this hook owns option-list
 * preparation, fallback stats, and CRUD wiring.
 */
export function useUserManagementController({
  canReadOrgUnits = true,
  canReadRoles = true
}: UseUserManagementControllerOptions = {}) {
  const usersQuery = useUsersQuery({
    perPage: 100,
    sortField: 'name',
    sortDir: 'asc'
  })

  const statsQuery = useUserStatsQuery()

  const companyStructureQuery = useCompanyStructureQuery({ enabled: canReadOrgUnits })

  const rolesQuery = useRolesQuery(
    {
      perPage: 100,
      sortField: 'name',
      sortDir: 'asc'
    },
    { enabled: canReadRoles }
  )

  const createMutation = useCreateUserMutation()

  const updateMutation = useUpdateUserMutation()

  const transitionMutation = useTransitionUserStatusMutation()

  const deleteMutation = useDeleteUserMutation()

  const users = useMemo(() => usersQuery.data ?? [], [usersQuery.data])

  const roleOptions = useMemo<UserRoleOption[]>(
    () =>
      (rolesQuery.data ?? [])
        .map(role => ({
          id: role.id,
          label:
            role.status === 'disabled'
              ? `${role.name}${role.orgUnit ? ` • ${role.orgUnit.name}` : ''} (Inactive)`
              : role.orgUnit
                ? `${role.name} • ${role.orgUnit.name}`
                : role.name,
          status: role.status,
          accessLevel: role.accessLevel
        }))
        .sort((a, b) => a.label.localeCompare(b.label)),
    [rolesQuery.data]
  )

  const orgUnitOptions = useMemo<UserOrgUnitOption[]>(
    () =>
      (companyStructureQuery.data?.data.units ?? [])
        .map(unit => ({
          id: unit.id,
          name: unit.name,
          code: unit.code,
          type: unit.type,
          status: unit.status,
          label:
            unit.status === 'inactive'
              ? `${unit.name} (${getOrgUnitTypeLabel(unit.type)}, inactive)`
              : `${unit.name} (${getOrgUnitTypeLabel(unit.type)})`
        }))
        .sort((a, b) => a.name.localeCompare(b.name)),
    [companyStructureQuery.data]
  )

  const createUser = useCallback(
    (payload: UserFormSubmitValues) => createMutation.mutateAsync(payload),
    [createMutation]
  )

  const updateUser = useCallback(
    (id: string, payload: UserFormSubmitValues) => updateMutation.mutateAsync({ userId: id, values: payload }),
    [updateMutation]
  )

  const setUsersStatus = useCallback(
    async (ids: string[], status: UserStatus) => {
      if (!ids.length) return

      await Promise.all(ids.map(userId => transitionMutation.mutateAsync({ userId, status })))
    },
    [transitionMutation]
  )

  const deleteUser = useCallback((id: string) => deleteMutation.mutateAsync(id), [deleteMutation])

  return {
    users,
    stats: statsQuery.data ?? {
      total: users.length,
      active: users.filter(user => user.status === 'enabled').length,
      inactive: users.filter(user => user.status === 'disabled').length
    },
    roleOptions,
    orgUnitOptions,
    createUser,
    updateUser,
    setUsersStatus,
    deleteUser,
    error: usersQuery.error ?? statsQuery.error ?? rolesQuery.error ?? companyStructureQuery.error ?? null,
    isBootstrapping: usersQuery.isLoading || rolesQuery.isLoading || companyStructureQuery.isLoading,
    isRefreshing:
      usersQuery.isFetching || statsQuery.isFetching || rolesQuery.isFetching || companyStructureQuery.isFetching,
    isSaving:
      createMutation.isPending || updateMutation.isPending || transitionMutation.isPending || deleteMutation.isPending
  }
}
