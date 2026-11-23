import type { z } from 'zod'

import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import { PERMISSION_KEYS } from '@/features/(protected)/users/permissions'

import {
  createEmptyRoleModulePermissions,
  flattenRolePermissions,
  getModuleActionKeysByAccessLevel,
  ROLE_PERMISSION_MODULES
} from '@/features/(protected)/users/roles/model/role-access-control'
import {
  roleApiErrorSchema,
  roleFormSchema,
  roleListQuerySchema,
  roleResponseSchema,
  rolesResponseSchema,
  roleStatsResponseSchema
} from '@/features/(protected)/users/roles/model/roles.schema'

import type { roleApiRecordSchema } from '@/features/(protected)/users/roles/model/roles.schema'
import type { PermissionKey } from '@/features/(protected)/users/permissions'
import type { RolePermissionModuleKey } from '@/features/(protected)/users/roles/model/role-access-control'
import type {
  RoleApiFieldErrors,
  RoleQuery,
  RoleRecord,
  RoleStats
} from '@/features/(protected)/users/roles/model/roles.types'

type RoleApiRecord = z.output<typeof roleApiRecordSchema>

export class RoleApiError extends Error {
  readonly status: number
  readonly fieldErrors: RoleApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: RoleApiFieldErrors | null = null) {
    super(message)
    this.name = 'RoleApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

/**
 * Converts the table filter status into the backend's active/inactive wording.
 */
function toApiStatus(status: RoleQuery['status']) {
  if (!status) return undefined

  return status === 'enabled' ? 'active' : 'inactive'
}

/**
 * Normalizes backend role status values for the UI.
 */
function toUiStatus(status: RoleApiRecord['status']): RoleRecord['status'] {
  return status === 'active' ? 'enabled' : 'disabled'
}

/**
 * Maps the backend scope enum into the simpler scope language used in the role
 * editor.
 */
function toUiPermissionScope(scope: RoleApiRecord['permission_scope']): RoleRecord['permissionScope'] {
  return scope === 'global' ? 'all' : 'page'
}

/**
 * Converts the editor's scope choice back into the backend enum.
 */
function toApiPermissionScope(
  scope: RoleRecord['permissionScope'],
  rawScope: RoleRecord['rawPermissionScope'] | null = null
): 'global' | 'department' | 'own' {
  // The current backend does not expose a dedicated "per-page" scope enum.
  // Preserve legacy own-scope roles until the UI exposes a dedicated editor.
  if (scope === 'all') {
    return 'global'
  }

  return rawScope === 'own' ? 'own' : 'department'
}

function toUiRoleKey(key: string) {
  return key.trim().replace(/-/g, '_').toUpperCase()
}

function toApiRoleKey(key: string) {
  return key.trim().toLowerCase()
}

/**
 * Keeps module actions in the same order the checklist UI renders them.
 */
function sortUiModuleActions(moduleKey: RolePermissionModuleKey, actions: Iterable<PermissionKey>): PermissionKey[] {
  const order =
    ROLE_PERMISSION_MODULES.find(module => module.key === moduleKey)?.actions.map(action => action.key) ??
    PERMISSION_KEYS

  const selected = new Set(actions)

  return order.filter(action => selected.has(action))
}

/**
 * Expands backend module verbs into the finer-grained action list shown in the
 * roles UI.
 */
function applyBackendModuleToUi(
  uiModulePermissions: ReturnType<typeof createEmptyRoleModulePermissions>,
  uiModuleKey: RolePermissionModuleKey,
  verbs: PermissionKey[]
): void {
  const selected = new Set<PermissionKey>(uiModulePermissions[uiModuleKey] ?? [])
  const readActions = getModuleActionKeysByAccessLevel(uiModuleKey, 'read')
  const writeActions = getModuleActionKeysByAccessLevel(uiModuleKey, 'write')
  const writeActionSet = new Set<PermissionKey>(writeActions)

  for (const verb of verbs) {
    if (verb === 'read') {
      readActions.forEach(action => selected.add(action))
      continue
    }

    if (verb === 'write') {
      writeActions.forEach(action => selected.add(action))
      continue
    }

    if (writeActionSet.has(verb)) {
      selected.add(verb)
    }
  }

  uiModulePermissions[uiModuleKey] = sortUiModuleActions(uiModuleKey, selected)
}

/**
 * Builds the role view model used by the roles page and editor.
 */
function mapApiRoleToRoleRecord(role: RoleApiRecord): RoleRecord {
  const uiModulePermissions = createEmptyRoleModulePermissions()

  for (const permissionModule of ROLE_PERMISSION_MODULES) {
    const verbs = role.module_permissions[permissionModule.key]

    if (!verbs?.length) {
      continue
    }

    applyBackendModuleToUi(uiModulePermissions, permissionModule.key, verbs)
  }

  return {
    id: role.id,
    tenantId: role.tenant_id,
    systemGenerated: role.system_generated,
    roleType: role.role_type,
    isAdminRole: role.is_admin_role,
    canEdit: role.can_edit,
    canDelete: role.can_delete,
    canDeactivate: role.can_deactivate,
    name: role.name,
    key: toUiRoleKey(role.key),
    department: role.org_unit?.name ?? role.department ?? null,
    orgUnit: role.org_unit
      ? {
          id: role.org_unit.id,
          companyId: role.org_unit.company_id,
          parentId: role.org_unit.parent_org_unit_id,
          type: role.org_unit.type,
          name: role.org_unit.name,
          status: role.org_unit.status,
          isRoot: role.org_unit.is_root
        }
      : null,
    status: toUiStatus(role.status),
    accessLevel: role.access_level,
    permissionScope: toUiPermissionScope(role.permission_scope),
    rawPermissionScope: role.permission_scope,
    modulePermissions: uiModulePermissions,
    rawModulePermissions: role.module_permissions,
    permissions: flattenRolePermissions(uiModulePermissions),
    description: role.description ?? '',
    membersCount: role.members_count ?? 0,
    members: [],
    createdAt: role.created_at,
    lastUpdated: role.updated_at
  }
}

/**
 * Rebuilds the outbound module-permission payload while preserving any legacy
 * backend modules the current frontend does not edit yet.
 */
function buildRoleModulePermissions(role: RoleRecord | null, values: z.output<typeof roleFormSchema>) {
  const modulePermissions: Record<string, PermissionKey[]> = {}
  const mappedBackendModules = new Set<string>(ROLE_PERMISSION_MODULES.map(module => module.key))

  for (const [moduleKey, verbs] of Object.entries(role?.rawModulePermissions ?? {})) {
    if (mappedBackendModules.has(moduleKey)) {
      continue
    }

    modulePermissions[moduleKey] = [...verbs]
  }

  for (const [uiModuleKey, selectedActions] of Object.entries(values.modulePermissions) as Array<
    [RolePermissionModuleKey, PermissionKey[]]
  >) {
    if (!selectedActions.length) {
      continue
    }

    modulePermissions[uiModuleKey] = sortUiModuleActions(uiModuleKey, selectedActions)
  }

  return modulePermissions
}

/**
 * Converts role-form values into the backend payload shape.
 */
function buildRolePayload(values: unknown, role: RoleRecord | null = null) {
  const parsed = roleFormSchema.parse(values)

  return {
    key: toApiRoleKey(parsed.key),
    name: parsed.name.trim(),
    department: null,
    org_unit_id: parsed.orgUnitId || null,
    status: parsed.status === 'enabled' ? 'active' : 'inactive',
    access_level: parsed.accessLevel,
    permission_scope: toApiPermissionScope(parsed.permissionScope, role?.rawPermissionScope ?? null),
    module_permissions: buildRoleModulePermissions(role, parsed),
    description: parsed.description.trim() || null
  }
}

/**
 * Converts the roles page query state into Laravel query parameters.
 */
function toParams(query: RoleQuery) {
  const parsed = roleListQuerySchema.parse(query)
  const params = new URLSearchParams()

  if (parsed.q) params.set('q', parsed.q)
  if (parsed.orgUnitId) params.set('org_unit_id', parsed.orgUnitId)

  if (parsed.status) params.set('status', toApiStatus(parsed.status) ?? '')
  params.set('page', String((parsed.page ?? 0) + 1))
  params.set('perPage', String(parsed.perPage ?? 100))

  if (parsed.sortField) params.set('sortField', parsed.sortField)
  if (parsed.sortDir) params.set('sortDir', parsed.sortDir)

  return params
}

/**
 * Loads the company role register.
 */
export async function fetchRoles(query: RoleQuery): Promise<RoleRecord[]> {
  const params = toParams(query)

  const res = await laravelBrowserClient.get('/api/v1/roles', { params: Object.fromEntries(params) })

  const payload = rolesResponseSchema.parse(res.data)

  return payload.data.map(mapApiRoleToRoleRecord)
}

/**
 * Loads the summary cards shown above the roles table.
 */
export async function fetchRoleStats(): Promise<RoleStats> {
  const res = await laravelBrowserClient.get('/api/v1/roles/stats')

  const payload = roleStatsResponseSchema.parse(res.data)

  return payload.data
}

/**
 * Creates a new role.
 */
export async function createRole(input: unknown): Promise<RoleRecord> {
  const payload = buildRolePayload(input)

  const res = await laravelBrowserClient.post('/api/v1/roles', payload)

  const response = roleResponseSchema.parse(res.data)

  return mapApiRoleToRoleRecord(response.data)
}

/**
 * Updates an existing role using its current backend-aware metadata.
 */
export async function updateRole(role: RoleRecord, input: unknown): Promise<RoleRecord> {
  const payload = buildRolePayload(input, role)

  const res = await laravelBrowserClient.put(`/api/v1/roles/${role.id}`, payload)

  const response = roleResponseSchema.parse(res.data)

  return mapApiRoleToRoleRecord(response.data)
}

/**
 * Activates or deactivates a role without reopening the editor.
 */
export async function transitionRoleStatus(roleId: string, status: RoleRecord['status']): Promise<RoleRecord> {
  const res = await laravelBrowserClient.patch(`/api/v1/roles/${roleId}/status`, {
    status: status === 'enabled' ? 'active' : 'inactive'
  })

  const response = roleResponseSchema.parse(res.data)

  return mapApiRoleToRoleRecord(response.data)
}

/**
 * Permanently removes a role when the backend allows it.
 */
export async function deleteRole(roleId: string): Promise<void> {
  await laravelBrowserClient.delete(`/api/v1/roles/${roleId}`)
}
