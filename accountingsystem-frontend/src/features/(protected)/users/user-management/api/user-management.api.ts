import type { z } from 'zod'

import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import { UNASSIGNED_DEPARTMENT_FILTER_VALUE } from '@/features/(protected)/users/shared/model/departments'
import {
  userApiErrorSchema,
  userFormSchema,
  userQuerySchema,
  userResponseSchema,
  usersResponseSchema,
  userStatsResponseSchema
} from '@/features/(protected)/users/user-management/model/user-management.schema'
import {
  getUserOperatingScopeLabel,
  getUserPrimaryOrgUnitLabel
} from '@/features/(protected)/users/user-management/model/user-management.types'

import type { PermissionKey } from '@/features/(protected)/users/permissions'
import type { userApiRecordSchema } from '@/features/(protected)/users/user-management/model/user-management.schema'
import type {
  UserApiFieldErrors,
  UserQuery,
  UserRecord,
  UserStats,
  UserStatus
} from '@/features/(protected)/users/user-management/model/user-management.types'

type UserApiRecord = z.output<typeof userApiRecordSchema>

export class UserManagementApiError extends Error {
  readonly status: number
  readonly fieldErrors: UserApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: UserApiFieldErrors | null = null) {
    super(message)
    this.name = 'UserManagementApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

/**
 * Translates the UI status filter into the backend's active/inactive wording.
 */
function toApiStatus(status: UserStatus | '') {
  if (!status) return undefined

  return status === 'enabled' ? 'active' : 'inactive'
}

/**
 * Normalizes backend user status values into the UI's enabled/disabled labels.
 */
function toUiStatus(status: 'active' | 'inactive'): UserStatus {
  return status === 'active' ? 'enabled' : 'disabled'
}

/**
 * Role responses expose permissions by module/action, but user-management
 * screens still need a flattened permission list for quick capability checks.
 */
function unionPermissions(role: UserApiRecord['roles'][number]): PermissionKey[] {
  const permissions = new Set<PermissionKey>()

  Object.values(role.module_permissions ?? {}).forEach(actions => {
    actions.forEach(action => permissions.add(action))
  })

  return Array.from(permissions)
}

/**
 * Converts the backend user record into the richer view model used by the user
 * table, profile drawer, and filters.
 */
function mapApiUser(user: UserApiRecord): UserRecord {
  const roles = user.roles.map(role => ({
    id: role.id,
    key: role.key,
    name: role.name,
    status: toUiStatus(role.status),
    accessLevel: role.access_level,
    permissions: unionPermissions(role)
  }))

  const permissions = Array.from(new Set(roles.flatMap(role => role.permissions)))
  const roleNames = roles.map(role => role.name)

  return {
    id: user.id,
    avatarUrl: user.avatar_url ?? '',
    fullName: user.name,
    email: user.email,
    isOwner: user.is_owner,
    isCurrentUser: user.is_current_user,
    canEdit: user.can_edit,
    canDelete: user.can_delete,
    membership: user.tenant_membership
      ? {
          id: user.tenant_membership.id,
          companyId: user.tenant_membership.tenant_id,
          status: toUiStatus(user.tenant_membership.status),
          joinedAt: user.tenant_membership.joined_at
        }
      : null,
    roleIds: roles.map(role => role.id),
    roleNames,
    roles,
    role: roleNames.join(', ') || 'No roles assigned',
    primaryOrgUnit: user.primary_org_unit ?? null,
    additionalOrgUnits: user.additional_org_units ?? [],
    roleAssignmentScope: user.role_assignment_scope ?? 'company',
    scopeOrgUnits:
      user.role_assignment_scope === 'primary_org_unit'
        ? user.primary_org_unit
          ? [user.primary_org_unit]
          : []
        : (user.scope_org_units ?? []),
    primaryOrgUnitLabel: getUserPrimaryOrgUnitLabel(user.primary_org_unit ?? null),
    operatingScopeLabel: getUserOperatingScopeLabel(
      user.role_assignment_scope ?? 'company',
      user.primary_org_unit ?? null,
      user.role_assignment_scope === 'primary_org_unit'
        ? user.primary_org_unit
          ? [user.primary_org_unit]
          : []
        : (user.scope_org_units ?? [])
    ),
    legacyDepartment: user.department ?? '',
    status: toUiStatus(user.status),
    accessLevel: user.access_level === 'write' ? 'write' : 'read',
    permissions,
    lastActive: user.last_active_at ?? user.updated_at,
    createdAt: user.created_at,
    updatedAt: user.updated_at
  }
}

/**
 * Builds the backend payload for create/update requests.
 *
 * The form keeps names and labels optimized for the UI, so this is where we
 * translate them back into the API contract.
 */
function buildUserPayload(values: unknown) {
  const parsed = userFormSchema.parse(values)

  return {
    name: parsed.fullName.trim(),
    email: parsed.email.trim().toLowerCase(),
    password: parsed.password.trim() || undefined,
    role_ids: parsed.roleIds,
    primary_org_unit_id: parsed.primaryOrgUnitId,
    additional_org_unit_ids: parsed.additionalOrgUnitIds,
    role_assignment_scope: parsed.roleAssignmentScope,
    scope_org_unit_ids: parsed.scopeOrgUnitIds,
    status: parsed.status === 'enabled' ? 'active' : 'inactive'
  }
}

/**
 * Converts the list-query shape used by the page into Laravel query-string
 * parameters.
 */
function toParams(query: UserQuery) {
  const parsed = userQuerySchema.parse(query)
  const params = new URLSearchParams()

  if (parsed.q) params.set('q', parsed.q)

  if (parsed.department) {
    params.set(
      'department',
      parsed.department === UNASSIGNED_DEPARTMENT_FILTER_VALUE ? UNASSIGNED_DEPARTMENT_FILTER_VALUE : parsed.department
    )
  }

  if (parsed.roleId) params.set('role_id', parsed.roleId)
  if (parsed.status) params.set('status', toApiStatus(parsed.status) ?? '')
  params.set('page', String((parsed.page ?? 0) + 1))
  params.set('perPage', String(parsed.perPage ?? 100))

  if (parsed.sortField) params.set('sortField', parsed.sortField)
  if (parsed.sortDir) params.set('sortDir', parsed.sortDir)

  return params
}

/**
 * Loads the company user register with filtering, sorting, and pagination.
 */
export async function fetchUsers(query: UserQuery): Promise<UserRecord[]> {
  const params = toParams(query)

  const res = await laravelBrowserClient.get('/api/v1/users', { params: Object.fromEntries(params) })

  const parsed = usersResponseSchema.parse(res.data)

  return parsed.data.map(mapApiUser)
}

/**
 * Loads the lightweight stats row shown above the user register.
 */
export async function fetchUserStats(): Promise<UserStats> {
  const res = await laravelBrowserClient.get('/api/v1/users/stats')

  const parsed = userStatsResponseSchema.parse(res.data)

  return parsed.data
}

/**
 * Creates a new company user through the frontend proxy.
 */
export async function createUser(values: unknown): Promise<UserRecord> {
  const res = await laravelBrowserClient.post('/api/v1/users', buildUserPayload(values))

  const parsed = userResponseSchema.parse(res.data)

  return mapApiUser(parsed.data)
}

/**
 * Updates an existing company user through the frontend proxy.
 */
export async function updateUser(userId: string, values: unknown): Promise<UserRecord> {
  const res = await laravelBrowserClient.put(`/api/v1/users/${userId}`, buildUserPayload(values))

  const parsed = userResponseSchema.parse(res.data)

  return mapApiUser(parsed.data)
}

/**
 * Activates or deactivates a user without editing the rest of the record.
 */
export async function transitionUserStatus(userId: string, status: UserStatus): Promise<UserRecord> {
  const res = await laravelBrowserClient.patch(`/api/v1/users/${userId}/status`, {
    status: status === 'enabled' ? 'active' : 'inactive'
  })

  const parsed = userResponseSchema.parse(res.data)

  return mapApiUser(parsed.data)
}

/**
 * Permanently removes a user record when the current role allows it.
 */
export async function deleteUser(userId: string): Promise<void> {
  await laravelBrowserClient.delete(`/api/v1/users/${userId}`)
}
