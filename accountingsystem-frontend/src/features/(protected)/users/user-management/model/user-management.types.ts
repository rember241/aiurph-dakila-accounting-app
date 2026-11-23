import type { PermissionKey } from '@/features/(protected)/users/permissions'
import type {
  OrgUnitStatus,
  OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

export const USER_STATUSES = ['enabled', 'disabled'] as const
export const USER_ACCESS_LEVELS = ['read', 'write'] as const
export const USER_ROLE_ASSIGNMENT_SCOPES = ['company', 'primary_org_unit', 'selected_org_units'] as const
export const USER_SORT_FIELDS = ['name', 'email', 'status', 'department', 'last_active_at', 'created_at'] as const
export const USER_SORT_DIRECTIONS = ['asc', 'desc'] as const

export type UserStatus = (typeof USER_STATUSES)[number]
export type UserAccessLevel = (typeof USER_ACCESS_LEVELS)[number]
export type UserRoleAssignmentScope = (typeof USER_ROLE_ASSIGNMENT_SCOPES)[number]
export type UserSortField = (typeof USER_SORT_FIELDS)[number]
export type UserSortDirection = (typeof USER_SORT_DIRECTIONS)[number]

export type UserRoleAssignment = {
  id: string
  key: string
  name: string
  status: UserStatus
  accessLevel: UserAccessLevel
  permissions: PermissionKey[]
}

export type UserOrgUnitSummary = {
  id: string
  name: string
  code: string
  type: OrgUnitType
  status: OrgUnitStatus
}

export type UserCompanyMembership = {
  id: string
  companyId: string
  status: UserStatus
  joinedAt: string | null
}

export type UserRecord = {
  id: string
  avatarUrl: string
  fullName: string
  email: string
  isOwner: boolean
  isCurrentUser: boolean
  canEdit: boolean
  canDelete: boolean
  membership: UserCompanyMembership | null
  roleIds: string[]
  roleNames: string[]
  roles: UserRoleAssignment[]
  role: string
  primaryOrgUnit: UserOrgUnitSummary | null
  additionalOrgUnits: UserOrgUnitSummary[]
  roleAssignmentScope: UserRoleAssignmentScope
  scopeOrgUnits: UserOrgUnitSummary[]
  primaryOrgUnitLabel: string
  operatingScopeLabel: string
  legacyDepartment: string
  status: UserStatus
  accessLevel: UserAccessLevel
  permissions: PermissionKey[]
  lastActive: string
  createdAt: string
  updatedAt: string
}

export type UserQueryFilters = {
  search: string
  status: UserStatus | ''
  accessLevel: UserAccessLevel | ''
  roleId: string
  primaryOrgUnitId: string
}

export type UserQuery = {
  q?: string
  status?: UserStatus | ''
  department?: string
  roleId?: string
  page?: number
  perPage?: number
  sortField?: UserSortField
  sortDir?: UserSortDirection
}

export type UserStats = {
  total: number
  active: number
  inactive: number
}

export type UserRoleOption = {
  id: string
  label: string
  status: UserStatus
  accessLevel: UserAccessLevel
}

export type UserOrgUnitOption = UserOrgUnitSummary & {
  label: string
}

export type UserApiFieldErrors = Record<string, string[] | undefined>

export function getUserRoleScopeLabel(scope: UserRoleAssignmentScope) {
  switch (scope) {
    case 'company':
      return 'Company-wide'
    case 'primary_org_unit':
      return 'Primary org unit'
    case 'selected_org_units':
      return 'Selected org units'
  }
}

export function getUserPrimaryOrgUnitLabel(orgUnit: UserOrgUnitSummary | null) {
  return orgUnit?.name ?? 'Not assigned'
}

export function summarizeUserOrgUnits(orgUnits: UserOrgUnitSummary[], emptyLabel = 'None') {
  if (orgUnits.length === 0) {
    return emptyLabel
  }

  if (orgUnits.length === 1) {
    return orgUnits[0].name
  }

  if (orgUnits.length === 2) {
    return `${orgUnits[0].name}, ${orgUnits[1].name}`
  }

  return `${orgUnits[0].name}, ${orgUnits[1].name} +${orgUnits.length - 2}`
}

export function getUserOperatingScopeLabel(
  scope: UserRoleAssignmentScope,
  primaryOrgUnit: UserOrgUnitSummary | null,
  scopeOrgUnits: UserOrgUnitSummary[]
) {
  switch (scope) {
    case 'company':
      return 'Across the full company'
    case 'primary_org_unit':
      return primaryOrgUnit ? `Primary org unit: ${primaryOrgUnit.name}` : 'Primary org unit only'
    case 'selected_org_units':
      return summarizeUserOrgUnits(scopeOrgUnits, 'Selected org units')
  }
}
