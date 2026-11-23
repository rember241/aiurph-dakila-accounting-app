import { useCallback, useMemo } from 'react'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import { useCompanyStructureQuery } from '@/features/(protected)/users/company-structure/api/company-structure.queries'
import { enforceScopePermissions } from '@/features/(protected)/users/roles/model/role-access-control'
import type { RoleFormSubmitValues } from '@/features/(protected)/users/roles/model/roles.schema'
import {
  type RoleAccessLevel,
  type RoleOrgUnitOption,
  type RoleRecord,
  type RoleStatus
} from '@/features/(protected)/users/roles/model/roles.types'
import {
  useCreateRoleMutation,
  useDeleteRoleMutation,
  useRolesQuery,
  useRoleStatsQuery,
  useTransitionRoleStatusMutation,
  useUpdateRoleMutation
} from '@/features/(protected)/users/roles/api/roles.queries'

type UseRolesControllerOptions = {
  canReadOrgUnits?: boolean
}

/**
 * Rebuilds a role record into the form payload used by the role editor when we
 * bulk-change access level from the list view.
 */
function createUpdatePayload(role: RoleRecord, accessLevel: RoleAccessLevel): RoleFormSubmitValues {
  const normalized = enforceScopePermissions(role.permissionScope, role.modulePermissions, accessLevel)

  return {
    name: role.name,
    key: role.key,
    orgUnitId: role.orgUnit?.id ?? '',
    status: role.status,
    accessLevel,
    permissionScope: role.permissionScope,
    modulePermissions: normalized.modulePermissions,
    permissions: normalized.permissions,
    description: role.description
  }
}

/**
 * Shared controller hook for the roles workspace.
 *
 * Besides wiring CRUD calls, it also normalizes org-unit options and the bulk
 * access-level action used by the table page.
 */
export function useRolesController({ canReadOrgUnits = true }: UseRolesControllerOptions = {}) {
  const companyStructureQuery = useCompanyStructureQuery({ enabled: canReadOrgUnits })

  const rolesQuery = useRolesQuery({
    perPage: 100,
    sortField: 'name',
    sortDir: 'asc'
  })

  const roleStatsQuery = useRoleStatsQuery()
  const createMutation = useCreateRoleMutation()
  const updateMutation = useUpdateRoleMutation()
  const transitionMutation = useTransitionRoleStatusMutation()
  const deleteMutation = useDeleteRoleMutation()

  const roles = useMemo(() => rolesQuery.data ?? [], [rolesQuery.data])
  const rolesById = useMemo(() => new Map(roles.map(role => [role.id, role])), [roles])

  const createRole = useCallback(
    (payload: RoleFormSubmitValues) => createMutation.mutateAsync(payload),
    [createMutation]
  )

  const updateRole = useCallback(
    (id: string, payload: RoleFormSubmitValues) => {
      const role = rolesById.get(id)

      if (!role) {
        throw new Error('Role not found.')
      }

      return updateMutation.mutateAsync({ role, values: payload })
    },
    [rolesById, updateMutation]
  )

  const setRolesStatus = useCallback(
    async (ids: string[], status: RoleStatus) => {
      if (!ids.length) return

      await Promise.all(ids.map(roleId => transitionMutation.mutateAsync({ roleId, status })))
    },
    [transitionMutation]
  )

  const setRolesAccess = useCallback(
    async (ids: string[], accessLevel: RoleAccessLevel) => {
      if (!ids.length) return

      await Promise.all(
        ids.map(async roleId => {
          const role = rolesById.get(roleId)

          if (!role) {
            return
          }

          await updateMutation.mutateAsync({
            role,
            values: createUpdatePayload(role, accessLevel)
          })
        })
      )
    },
    [rolesById, updateMutation]
  )

  const deleteRole = useCallback((roleId: string) => deleteMutation.mutateAsync(roleId), [deleteMutation])

  const orgUnitOptions = useMemo<RoleOrgUnitOption[]>(
    () =>
      (companyStructureQuery.data?.data.units ?? [])
        .map(unit => ({
          id: unit.id,
          companyId: unit.companyId,
          parentId: unit.parentId,
          type: unit.type,
          name: unit.name,
          status: unit.status,
          isRoot: unit.isRoot,
          label:
            unit.status === 'inactive'
              ? `${unit.name} (${getOrgUnitTypeLabel(unit.type)}, inactive)`
              : `${unit.name} (${getOrgUnitTypeLabel(unit.type)})`
        }))
        .sort((left, right) => left.name.localeCompare(right.name)),
    [companyStructureQuery.data]
  )

  return {
    roles,
    stats: roleStatsQuery.data ?? {
      total: roles.length,
      active: roles.filter(role => role.status === 'enabled').length,
      inactive: roles.filter(role => role.status === 'disabled').length
    },
    error: rolesQuery.error ?? roleStatsQuery.error ?? companyStructureQuery.error ?? null,
    isBootstrapping: rolesQuery.isLoading || companyStructureQuery.isLoading,
    isRefreshing: rolesQuery.isFetching || roleStatsQuery.isFetching || companyStructureQuery.isFetching,
    isSaving:
      createMutation.isPending || updateMutation.isPending || transitionMutation.isPending || deleteMutation.isPending,
    orgUnitOptions,
    createRole,
    updateRole,
    setRolesStatus,
    setRolesAccess,
    deleteRole
  }
}
