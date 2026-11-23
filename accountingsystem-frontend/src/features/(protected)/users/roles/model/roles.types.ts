import type { PermissionKey } from '@/features/(protected)/users/permissions'
import type {
  RoleModulePermissions,
  RolePermissionScope
} from '@/features/(protected)/users/roles/model/role-access-control'
import type {
  OrgUnitStatus,
  OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

export const ROLE_STATUSES = ['enabled', 'disabled'] as const
export const ROLE_ACCESS_LEVELS = ['read', 'write'] as const
export const ROLE_SORT_FIELDS = ['name', 'key', 'status', 'department', 'updated_at', 'created_at'] as const
export const ROLE_SORT_DIRECTIONS = ['asc', 'desc'] as const

export type RoleStatus = (typeof ROLE_STATUSES)[number]
export type RoleAccessLevel = (typeof ROLE_ACCESS_LEVELS)[number]
export type RoleSortField = (typeof ROLE_SORT_FIELDS)[number]
export type RoleSortDirection = (typeof ROLE_SORT_DIRECTIONS)[number]
export type RoleRawPermissionScope = 'global' | 'department' | 'own'

export type RoleMember = {
  id: string
  fullName: string
  email: string
}

export type RoleOrgUnitSummary = {
  id: string
  companyId: string
  parentId: string | null
  type: OrgUnitType
  name: string
  status: OrgUnitStatus
  isRoot: boolean
}

export type RoleRecord = {
  id: string
  tenantId: string | null
  systemGenerated: boolean
  roleType: 'system' | 'custom'
  isAdminRole: boolean
  canEdit: boolean
  canDelete: boolean
  canDeactivate: boolean
  name: string
  key: string
  department: string | null
  orgUnit: RoleOrgUnitSummary | null
  status: RoleStatus
  accessLevel: RoleAccessLevel
  permissionScope: RolePermissionScope
  rawPermissionScope: RoleRawPermissionScope
  modulePermissions: RoleModulePermissions
  rawModulePermissions: Record<string, PermissionKey[]>
  permissions: PermissionKey[]
  description: string
  membersCount: number
  members: RoleMember[]
  createdAt: string
  lastUpdated: string
}

export type RoleQuery = {
  q?: string
  status?: RoleStatus | ''
  orgUnitId?: string
  page?: number
  perPage?: number
  sortField?: RoleSortField
  sortDir?: RoleSortDirection
}

export type RoleStats = {
  total: number
  active: number
  inactive: number
}

export type RoleApiFieldErrors = Record<string, string[] | undefined>

export type RoleOrgUnitOption = RoleOrgUnitSummary & {
  label: string
}

export function getRoleOrgUnitLabel(role: Pick<RoleRecord, 'orgUnit'>) {
  return role.orgUnit?.name ?? 'Company-wide'
}

export function isProtectedRole(role: Pick<RoleRecord, 'key'> & Partial<Pick<RoleRecord, 'isAdminRole'>>) {
  return role.isAdminRole ?? role.key.trim().toUpperCase() === 'ADMIN'
}

export function canEditRole(role: Pick<RoleRecord, 'key'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canEdit'>>) {
  return role.canEdit ?? !isProtectedRole(role)
}

export function getRoleEditGuardReason(
  role: Pick<RoleRecord, 'key'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canEdit'>>
) {
  if (!canEditRole(role) && isProtectedRole(role)) {
    return 'Only the company owner can edit the admin role'
  }

  return null
}

export function getRoleDeleteGuardReason(
  role: Pick<RoleRecord, 'key' | 'membersCount'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canDelete'>>
) {
  if ((role.canDelete ?? true) === false && isProtectedRole(role)) {
    return 'Admin role cannot be deleted'
  }

  if (role.membersCount > 0) {
    return 'Roles with assigned members cannot be deleted'
  }

  return null
}

export function canDeleteRole(
  role: Pick<RoleRecord, 'key' | 'membersCount'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canDelete'>>
) {
  return (role.canDelete ?? true) && getRoleDeleteGuardReason(role) === null
}

export function canDeactivateRole(
  role: Pick<RoleRecord, 'key'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canDeactivate'>>
) {
  return role.canDeactivate ?? !isProtectedRole(role)
}

export function canSelectRoleForBulk(
  role: Pick<RoleRecord, 'key'> & Partial<Pick<RoleRecord, 'isAdminRole' | 'canDeactivate'>>
) {
  return canDeactivateRole(role)
}
