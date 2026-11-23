import { randomUUID } from 'node:crypto'

import {
  canAssignParentType,
  createEmptyCompanyStructureStats
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type {
  CompanyStructureStats,
  OrgUnitFormValues,
  OrgUnitRecord,
  OrgUnitStatus,
  OrgUnitTreeNode,
  OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

type CompanyStructureStoreErrorOptions = {
  status?: number
  fieldErrors?: Record<string, string[] | undefined> | null
}

type StoredOrgUnit = OrgUnitRecord
type StoredOrgUnitInput = Omit<StoredOrgUnit, 'isRoot' | 'membersCount' | 'linkedRolesCount'> &
  Partial<Pick<StoredOrgUnit, 'isRoot' | 'membersCount' | 'linkedRolesCount'>>

const store = new Map<string, StoredOrgUnit[]>()

const TYPE_ORDER: Record<OrgUnitType, number> = {
  head_office: 0,
  branch: 1,
  department: 2,
  team: 3
}

export class CompanyStructureStoreError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[] | undefined> | null

  constructor(message: string, options: CompanyStructureStoreErrorOptions = {}) {
    super(message)
    this.name = 'CompanyStructureStoreError'
    this.status = options.status ?? 422
    this.fieldErrors = options.fieldErrors ?? null
  }
}

function nowIso() {
  return new Date().toISOString()
}

function cloneUnit(unit: StoredOrgUnit): StoredOrgUnit {
  return { ...unit }
}

function createStoredOrgUnit(unit: StoredOrgUnitInput): StoredOrgUnit {
  return {
    isRoot: false,
    membersCount: 0,
    linkedRolesCount: 0,
    ...unit
  }
}

function compareUnits(a: StoredOrgUnit, b: StoredOrgUnit) {
  const typeOrder = TYPE_ORDER[a.type] - TYPE_ORDER[b.type]

  if (typeOrder !== 0) {
    return typeOrder
  }

  return a.name.localeCompare(b.name)
}

function buildSeedUnits(companyId: string): StoredOrgUnit[] {
  const timestamp = nowIso()
  const headOfficeId = randomUUID()
  const metroBranchId = randomUUID()
  const southBranchId = randomUUID()
  const financeDeptId = randomUUID()
  const operationsDeptId = randomUUID()

  const seedUnits: StoredOrgUnit[] = [
    createStoredOrgUnit({
      id: headOfficeId,
      companyId,
      parentId: null,
      name: 'Head Office',
      code: 'HO',
      type: 'head_office',
      status: 'active',
      isRoot: true,
      location: 'Main corporate office',
      description: 'Central company oversight and shared administration.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: metroBranchId,
      companyId,
      parentId: headOfficeId,
      name: 'Metro Branch',
      code: 'BR-METRO',
      type: 'branch',
      status: 'active',
      location: 'Metro Manila',
      description: 'Primary customer-facing branch operation.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: southBranchId,
      companyId,
      parentId: headOfficeId,
      name: 'South Branch',
      code: 'BR-SOUTH',
      type: 'branch',
      status: 'active',
      location: 'Cebu',
      description: 'Regional service and logistics branch.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: financeDeptId,
      companyId,
      parentId: headOfficeId,
      name: 'Finance Department',
      code: 'DEPT-FIN',
      type: 'department',
      status: 'active',
      location: 'Head Office',
      description: 'Treasury, reporting, and controllership.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: operationsDeptId,
      companyId,
      parentId: metroBranchId,
      name: 'Operations Department',
      code: 'DEPT-OPS',
      type: 'department',
      status: 'active',
      location: 'Metro Branch',
      description: 'Daily branch execution and inventory support.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: randomUUID(),
      companyId,
      parentId: financeDeptId,
      name: 'Accounts Payable Team',
      code: 'TEAM-AP',
      type: 'team',
      status: 'active',
      location: 'Head Office',
      description: 'Vendor payments and invoice processing.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: randomUUID(),
      companyId,
      parentId: financeDeptId,
      name: 'Accounts Receivable Team',
      code: 'TEAM-AR',
      type: 'team',
      status: 'active',
      location: 'Head Office',
      description: 'Collections and customer balance monitoring.',
      createdAt: timestamp,
      updatedAt: timestamp
    }),
    createStoredOrgUnit({
      id: randomUUID(),
      companyId,
      parentId: operationsDeptId,
      name: 'Warehouse Team',
      code: 'TEAM-WH',
      type: 'team',
      status: 'inactive',
      location: 'Metro Branch',
      description: 'Receiving, storage, and stock handling.',
      createdAt: timestamp,
      updatedAt: timestamp
    })
  ]

  return seedUnits.sort(compareUnits)
}

function ensureCompanyUnits(companyId: string) {
  const existing = store.get(companyId)

  if (existing) {
    return existing
  }

  const seeded = buildSeedUnits(companyId)

  store.set(companyId, seeded)

  return seeded
}

function getCompanyUnits(companyId: string) {
  return ensureCompanyUnits(companyId)
}

function assertUniqueCode(units: StoredOrgUnit[], code: string, ignoreId?: string) {
  const duplicate = units.find(unit => unit.code.toUpperCase() === code.toUpperCase() && unit.id !== ignoreId)

  if (!duplicate) {
    return
  }

  throw new CompanyStructureStoreError('Org unit code must be unique within the company.', {
    fieldErrors: {
      code: ['Org unit code must be unique within the company.']
    }
  })
}

function isDescendant(units: StoredOrgUnit[], unitId: string, possibleAncestorId: string) {
  let current = units.find(unit => unit.id === unitId)

  while (current?.parentId) {
    if (current.parentId === possibleAncestorId) {
      return true
    }

    current = units.find(unit => unit.id === current?.parentId)
  }

  return false
}

function assertValidParent(units: StoredOrgUnit[], type: OrgUnitType, parentId: string | null, currentUnitId?: string) {
  if (!parentId) {
    if (!canAssignParentType(type, null)) {
      throw new CompanyStructureStoreError('This org unit type must be placed under a parent unit.', {
        fieldErrors: {
          parentId: ['Select a valid parent unit for this org unit type.']
        }
      })
    }

    return
  }

  if (currentUnitId && parentId === currentUnitId) {
    throw new CompanyStructureStoreError('An org unit cannot report to itself.', {
      fieldErrors: {
        parentId: ['An org unit cannot report to itself.']
      }
    })
  }

  if (currentUnitId && isDescendant(units, parentId, currentUnitId)) {
    throw new CompanyStructureStoreError('Select a parent outside the current hierarchy branch.', {
      fieldErrors: {
        parentId: ['Select a parent outside the current hierarchy branch.']
      }
    })
  }

  const parent = units.find(unit => unit.id === parentId)

  if (!parent) {
    throw new CompanyStructureStoreError('Parent org unit was not found.', {
      status: 404,
      fieldErrors: {
        parentId: ['Parent org unit was not found.']
      }
    })
  }

  if (!canAssignParentType(type, parent.type)) {
    throw new CompanyStructureStoreError('This org unit type cannot be placed under the selected parent.', {
      fieldErrors: {
        parentId: ['This org unit type cannot be placed under the selected parent.']
      }
    })
  }

  if (parent.status !== 'active') {
    throw new CompanyStructureStoreError('Inactive org units cannot receive new or moved children.', {
      fieldErrors: {
        parentId: ['Select an active parent org unit.']
      }
    })
  }
}

function assertChildrenRemainValid(units: StoredOrgUnit[], unitId: string, nextType: OrgUnitType) {
  const childUnits = units.filter(unit => unit.parentId === unitId)

  for (const child of childUnits) {
    if (!canAssignParentType(child.type, nextType)) {
      throw new CompanyStructureStoreError('Change the child hierarchy before updating this org unit type.', {
        fieldErrors: {
          type: ['Change the child hierarchy before updating this org unit type.']
        }
      })
    }
  }
}

function collectDescendantIds(units: StoredOrgUnit[], unitId: string): string[] {
  const childIds = units.filter(unit => unit.parentId === unitId).map(unit => unit.id)

  return childIds.flatMap(childId => [childId, ...collectDescendantIds(units, childId)])
}

function buildHierarchy(units: StoredOrgUnit[]): OrgUnitTreeNode[] {
  const childrenByParent = new Map<string | null, StoredOrgUnit[]>()

  for (const unit of units) {
    const bucket = childrenByParent.get(unit.parentId) ?? []

    bucket.push(unit)
    childrenByParent.set(unit.parentId, bucket)
  }

  for (const bucket of childrenByParent.values()) {
    bucket.sort(compareUnits)
  }

  const buildNodes = (parentId: string | null, depth: number): OrgUnitTreeNode[] => {
    const children = childrenByParent.get(parentId) ?? []

    return children.map(unit => {
      const childNodes = buildNodes(unit.id, depth + 1)

      return {
        ...cloneUnit(unit),
        children: childNodes,
        depth,
        directChildrenCount: childNodes.length
      }
    })
  }

  return buildNodes(null, 0)
}

function buildStats(units: StoredOrgUnit[]): CompanyStructureStats {
  const stats = createEmptyCompanyStructureStats()

  for (const unit of units) {
    stats.total += 1
    stats[unit.status === 'active' ? 'active' : 'inactive'] += 1

    switch (unit.type) {
      case 'head_office':
        stats.headOffice += 1
        break
      case 'branch':
        stats.branches += 1
        break
      case 'department':
        stats.departments += 1
        break
      case 'team':
        stats.teams += 1
        break
    }
  }

  return stats
}

export function listCompanyOrgUnits(companyId: string) {
  const units = getCompanyUnits(companyId).map(cloneUnit).sort(compareUnits)

  return {
    units,
    hierarchy: buildHierarchy(units),
    stats: buildStats(units)
  }
}

export function createCompanyOrgUnit(companyId: string, payload: OrgUnitFormValues) {
  const units = getCompanyUnits(companyId)

  assertUniqueCode(units, payload.code)
  assertValidParent(units, payload.type, payload.parentId)

  const timestamp = nowIso()

  const nextUnit = createStoredOrgUnit({
    id: randomUUID(),
    companyId,
    parentId: payload.parentId,
    name: payload.name,
    code: payload.code.toUpperCase(),
    type: payload.type,
    status: 'active',
    location: payload.location || null,
    description: payload.description || null,
    createdAt: timestamp,
    updatedAt: timestamp
  })

  units.push(nextUnit)
  units.sort(compareUnits)

  return cloneUnit(nextUnit)
}

export function updateCompanyOrgUnit(companyId: string, orgUnitId: string, payload: OrgUnitFormValues) {
  const units = getCompanyUnits(companyId)
  const unitIndex = units.findIndex(unit => unit.id === orgUnitId)

  if (unitIndex < 0) {
    throw new CompanyStructureStoreError('Org unit was not found.', { status: 404 })
  }

  const current = units[unitIndex]

  assertUniqueCode(units, payload.code, orgUnitId)
  assertChildrenRemainValid(units, orgUnitId, payload.type)
  assertValidParent(units, payload.type, payload.parentId, orgUnitId)

  const nextUnit: StoredOrgUnit = {
    ...current,
    parentId: payload.parentId,
    name: payload.name,
    code: payload.code.toUpperCase(),
    type: payload.type,
    location: payload.location || null,
    description: payload.description || null,
    updatedAt: nowIso()
  }

  units[unitIndex] = nextUnit
  units.sort(compareUnits)

  return cloneUnit(nextUnit)
}

export function updateCompanyOrgUnitStatus(companyId: string, orgUnitId: string, status: OrgUnitStatus) {
  const units = getCompanyUnits(companyId)
  const target = units.find(unit => unit.id === orgUnitId)

  if (!target) {
    throw new CompanyStructureStoreError('Org unit was not found.', { status: 404 })
  }

  if (status === 'active' && target.parentId) {
    const parent = units.find(unit => unit.id === target.parentId)

    if (parent?.status !== 'active') {
      throw new CompanyStructureStoreError('Activate the parent org unit first.', {
        fieldErrors: {
          status: ['Activate the parent org unit first.']
        }
      })
    }
  }

  const affectedIds = status === 'inactive' ? [orgUnitId, ...collectDescendantIds(units, orgUnitId)] : [orgUnitId]
  const timestamp = nowIso()

  for (const unit of units) {
    if (affectedIds.includes(unit.id)) {
      unit.status = status
      unit.updatedAt = timestamp
    }
  }

  return cloneUnit(units.find(unit => unit.id === orgUnitId)!)
}
