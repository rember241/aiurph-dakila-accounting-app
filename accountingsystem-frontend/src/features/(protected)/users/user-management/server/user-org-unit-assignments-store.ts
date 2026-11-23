import type { OrgUnitRecord } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import type { UserRoleAssignmentScope } from '@/features/(protected)/users/user-management/model/user-management.types'

type UserOrgUnitAssignmentInput = {
  primaryOrgUnitId: string
  additionalOrgUnitIds: string[]
  roleAssignmentScope: UserRoleAssignmentScope
  scopeOrgUnitIds: string[]
}

type StoredUserOrgUnitAssignment = {
  primaryOrgUnitId: string
  additionalOrgUnitIds: string[]
  roleAssignmentScope: UserRoleAssignmentScope
  scopeOrgUnitIds: string[]
  updatedAt: string
}

type UserOrgUnitAssignmentSummary = {
  primaryOrgUnit: {
    id: string
    name: string
    code: string
    type: OrgUnitRecord['type']
    status: OrgUnitRecord['status']
  } | null
  additionalOrgUnits: Array<{
    id: string
    name: string
    code: string
    type: OrgUnitRecord['type']
    status: OrgUnitRecord['status']
  }>
  roleAssignmentScope: UserRoleAssignmentScope
  scopeOrgUnits: Array<{
    id: string
    name: string
    code: string
    type: OrgUnitRecord['type']
    status: OrgUnitRecord['status']
  }>
}

type UserOrgUnitAssignmentStoreErrorOptions = {
  status?: number
  fieldErrors?: Record<string, string[] | undefined> | null
}

const store = new Map<string, Map<string, StoredUserOrgUnitAssignment>>()

export class UserOrgUnitAssignmentStoreError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[] | undefined> | null

  constructor(message: string, options: UserOrgUnitAssignmentStoreErrorOptions = {}) {
    super(message)
    this.name = 'UserOrgUnitAssignmentStoreError'
    this.status = options.status ?? 422
    this.fieldErrors = options.fieldErrors ?? null
  }
}

function nowIso() {
  return new Date().toISOString()
}

function toSummary(unit: OrgUnitRecord) {
  return {
    id: unit.id,
    name: unit.name,
    code: unit.code,
    type: unit.type,
    status: unit.status
  }
}

function findOrgUnit(units: OrgUnitRecord[], orgUnitId: string) {
  return units.find(unit => unit.id === orgUnitId) ?? null
}

function normalizeIds(ids: string[]) {
  return Array.from(new Set(ids.filter(Boolean)))
}

function resolveLegacyPrimaryOrgUnit(units: OrgUnitRecord[], legacyDepartment: string | null | undefined) {
  const normalized = legacyDepartment?.trim().toLowerCase()

  if (!normalized) {
    return null
  }

  return (
    units.find(unit => unit.name.trim().toLowerCase() === normalized) ??
    units.find(unit => unit.code.trim().toLowerCase() === normalized) ??
    units.find(unit => unit.name.trim().toLowerCase().includes(normalized)) ??
    null
  )
}

export function validateUserOrgUnitAssignment(units: OrgUnitRecord[], input: UserOrgUnitAssignmentInput) {
  const primaryOrgUnit = findOrgUnit(units, input.primaryOrgUnitId)

  if (!primaryOrgUnit) {
    throw new UserOrgUnitAssignmentStoreError('Select a valid primary org unit.', {
      fieldErrors: {
        primary_org_unit_id: ['Select a valid primary org unit.']
      }
    })
  }

  const additionalOrgUnitIds = normalizeIds(input.additionalOrgUnitIds).filter(
    orgUnitId => orgUnitId !== primaryOrgUnit.id
  )

  const additionalOrgUnits = additionalOrgUnitIds.map(orgUnitId => findOrgUnit(units, orgUnitId))

  if (additionalOrgUnits.some(orgUnit => !orgUnit)) {
    throw new UserOrgUnitAssignmentStoreError('One or more additional org units could not be found.', {
      fieldErrors: {
        additional_org_unit_ids: ['Choose additional org units from the company structure.']
      }
    })
  }

  const assignedOrgUnitIds = new Set([primaryOrgUnit.id, ...additionalOrgUnitIds])
  const normalizedScopeOrgUnitIds = normalizeIds(input.scopeOrgUnitIds)

  if (input.roleAssignmentScope === 'selected_org_units' && normalizedScopeOrgUnitIds.length === 0) {
    throw new UserOrgUnitAssignmentStoreError('Select at least one scoped org unit.', {
      fieldErrors: {
        scope_org_unit_ids: ['Select at least one scoped org unit.']
      }
    })
  }

  if (normalizedScopeOrgUnitIds.some(orgUnitId => !assignedOrgUnitIds.has(orgUnitId))) {
    throw new UserOrgUnitAssignmentStoreError('Scoped org units must come from the assigned org unit list.', {
      fieldErrors: {
        scope_org_unit_ids: ['Scoped org units must come from the assigned org unit list.']
      }
    })
  }

  return {
    primaryOrgUnit,
    primaryOrgUnitId: primaryOrgUnit.id,
    primaryOrgUnitName: primaryOrgUnit.name.slice(0, 80),
    additionalOrgUnitIds,
    roleAssignmentScope: input.roleAssignmentScope,
    scopeOrgUnitIds:
      input.roleAssignmentScope === 'selected_org_units'
        ? normalizedScopeOrgUnitIds
        : input.roleAssignmentScope === 'primary_org_unit'
          ? [primaryOrgUnit.id]
          : []
  }
}

function getCompanyStore(companyId: string) {
  const existing = store.get(companyId)

  if (existing) {
    return existing
  }

  const nextStore = new Map<string, StoredUserOrgUnitAssignment>()

  store.set(companyId, nextStore)

  return nextStore
}

export function saveUserOrgUnitAssignment(
  companyId: string,
  userId: string,
  input: UserOrgUnitAssignmentInput,
  units: OrgUnitRecord[]
) {
  const normalized = validateUserOrgUnitAssignment(units, input)

  getCompanyStore(companyId).set(userId, {
    primaryOrgUnitId: normalized.primaryOrgUnitId,
    additionalOrgUnitIds: normalized.additionalOrgUnitIds,
    roleAssignmentScope: normalized.roleAssignmentScope,
    scopeOrgUnitIds: normalized.scopeOrgUnitIds,
    updatedAt: nowIso()
  })

  return normalized
}

export function deleteUserOrgUnitAssignment(companyId: string, userId: string) {
  const companyStore = store.get(companyId)

  if (!companyStore) {
    return
  }

  companyStore.delete(userId)
}

export function resolveUserOrgUnitAssignment(
  companyId: string,
  userId: string,
  legacyDepartment: string | null | undefined,
  units: OrgUnitRecord[]
): UserOrgUnitAssignmentSummary {
  const stored = store.get(companyId)?.get(userId)

  const primaryOrgUnit =
    (stored ? findOrgUnit(units, stored.primaryOrgUnitId) : resolveLegacyPrimaryOrgUnit(units, legacyDepartment)) ??
    null

  const additionalOrgUnits = (stored?.additionalOrgUnitIds ?? [])
    .map(orgUnitId => findOrgUnit(units, orgUnitId))
    .filter((orgUnit): orgUnit is OrgUnitRecord => !!orgUnit)

  const roleAssignmentScope: UserRoleAssignmentScope =
    stored?.roleAssignmentScope ?? (primaryOrgUnit ? 'primary_org_unit' : 'company')

  const scopeOrgUnits =
    roleAssignmentScope === 'company'
      ? []
      : roleAssignmentScope === 'primary_org_unit'
        ? primaryOrgUnit
          ? [primaryOrgUnit]
          : []
        : (stored?.scopeOrgUnitIds ?? [])
            .map(orgUnitId => findOrgUnit(units, orgUnitId))
            .filter((orgUnit): orgUnit is OrgUnitRecord => !!orgUnit)

  return {
    primaryOrgUnit: primaryOrgUnit ? toSummary(primaryOrgUnit) : null,
    additionalOrgUnits: additionalOrgUnits.map(toSummary),
    roleAssignmentScope,
    scopeOrgUnits: scopeOrgUnits.map(toSummary)
  }
}
