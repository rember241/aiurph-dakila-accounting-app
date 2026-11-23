export const ORG_UNIT_TYPES = ['head_office', 'branch', 'department', 'team'] as const
export const ORG_UNIT_STATUSES = ['active', 'inactive'] as const

export type OrgUnitType = (typeof ORG_UNIT_TYPES)[number]
export type OrgUnitStatus = (typeof ORG_UNIT_STATUSES)[number]

export type OrgUnitRecord = {
  id: string
  companyId: string
  parentId: string | null
  name: string
  code: string
  type: OrgUnitType
  status: OrgUnitStatus
  isRoot: boolean
  membersCount: number
  linkedRolesCount: number
  location: string | null
  description: string | null
  createdAt: string
  updatedAt: string
}

export type OrgUnitTreeNode = OrgUnitRecord & {
  children: OrgUnitTreeNode[]
  depth: number
  directChildrenCount: number
}

export type CompanyStructureStats = {
  total: number
  active: number
  inactive: number
  headOffice: number
  branches: number
  departments: number
  teams: number
}

export type CompanyStructureResponse = {
  data: {
    units: OrgUnitRecord[]
    hierarchy: OrgUnitTreeNode[]
    stats: CompanyStructureStats
  }
}

export type OrgUnitMutationResponse = {
  data: OrgUnitRecord
  message: string
}

export type OrgUnitFormValues = {
  name: string
  code: string
  type: OrgUnitType
  parentId: string | null
  location: string
  description: string
}

export type OrgUnitStatusUpdateInput = {
  status: OrgUnitStatus
}

export type OrgUnitDeleteGuardInput = Pick<OrgUnitRecord, 'isRoot' | 'membersCount'> & {
  directChildrenCount: number
}

export type OrgUnitApiFieldErrors = Record<string, string[] | undefined>

export type OrgUnitParentOption = {
  id: string
  name: string
  code: string
  type: OrgUnitType
}

export type CompanyStructureRow = OrgUnitRecord & {
  parentName: string | null
  directChildrenCount: number
}

export function getOrgUnitDeleteGuardReason(orgUnit: OrgUnitDeleteGuardInput) {
  if (orgUnit.isRoot) {
    return 'The root organization unit cannot be deleted.'
  }

  if (orgUnit.directChildrenCount > 0) {
    return 'Org units with child units cannot be deleted.'
  }

  if (orgUnit.membersCount > 0) {
    return 'Org units with assigned members cannot be deleted.'
  }

  return null
}

export function canDeleteOrgUnit(orgUnit: OrgUnitDeleteGuardInput) {
  return getOrgUnitDeleteGuardReason(orgUnit) === null
}

export function getOrgUnitTypeLabel(type: OrgUnitType) {
  switch (type) {
    case 'head_office':
      return 'Head Office'
    case 'branch':
      return 'Branch'
    case 'department':
      return 'Department'
    case 'team':
      return 'Team'
  }
}

export function getOrgUnitTypeIcon(type: OrgUnitType) {
  switch (type) {
    case 'head_office':
      return 'bx-building-house'
    case 'branch':
      return 'bx-buildings'
    case 'department':
      return 'bx-sitemap'
    case 'team':
      return 'bx-group'
  }
}

export function getOrgUnitStatusLabel(status: OrgUnitStatus) {
  return status === 'active' ? 'Active' : 'Inactive'
}

export function getAllowedParentTypes(type: OrgUnitType): OrgUnitType[] {
  switch (type) {
    case 'head_office':
      return []
    case 'branch':
      return ['head_office']
    case 'department':
      return ['head_office', 'branch']
    case 'team':
      return ['branch', 'department']
  }
}

export function canAssignParentType(type: OrgUnitType, parentType: OrgUnitType | null) {
  if (parentType === null) {
    return type === 'head_office' || type === 'branch'
  }

  return getAllowedParentTypes(type).includes(parentType)
}

export function createEmptyCompanyStructureStats(): CompanyStructureStats {
  return {
    total: 0,
    active: 0,
    inactive: 0,
    headOffice: 0,
    branches: 0,
    departments: 0,
    teams: 0
  }
}
