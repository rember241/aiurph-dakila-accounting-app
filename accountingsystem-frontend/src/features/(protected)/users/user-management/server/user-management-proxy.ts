import type { z } from 'zod'

import type { userApiRecordSchema } from '@/features/(protected)/users/user-management/model/user-management.schema'

import {
  userMutationPayloadSchema,
  userResponseSchema,
  usersResponseSchema
} from '@/features/(protected)/users/user-management/model/user-management.schema'

import {
  resolveUserOrgUnitAssignment,
  validateUserOrgUnitAssignment
} from '@/features/(protected)/users/user-management/server/user-org-unit-assignments-store'
import type { OrgUnitRecord } from '@/features/(protected)/users/company-structure/model/company-structure.types'

type UserApiRecord = z.output<typeof userApiRecordSchema>
type UserMutationPayload = z.output<typeof userMutationPayloadSchema>
type UserResponsePayload = z.output<typeof userResponseSchema>
type UsersResponsePayload = z.output<typeof usersResponseSchema>

export function parseUserMutationPayload(raw: unknown) {
  return userMutationPayloadSchema.safeParse(raw)
}

export function buildUpstreamUserPayload(companyId: string, orgUnits: OrgUnitRecord[], payload: UserMutationPayload) {
  const normalizedAssignment = validateUserOrgUnitAssignment(orgUnits, {
    primaryOrgUnitId: payload.primary_org_unit_id,
    additionalOrgUnitIds: payload.additional_org_unit_ids,
    roleAssignmentScope: payload.role_assignment_scope,
    scopeOrgUnitIds: payload.scope_org_unit_ids
  })

  return {
    normalizedAssignment,
    upstreamPayload: {
      name: payload.name.trim(),
      email: payload.email.trim().toLowerCase(),
      password: payload.password?.trim() || undefined,
      role_ids: payload.role_ids,
      primary_org_unit_id: normalizedAssignment.primaryOrgUnitId,
      org_unit_ids: [normalizedAssignment.primaryOrgUnitId, ...normalizedAssignment.additionalOrgUnitIds],
      department: normalizedAssignment.primaryOrgUnitName,
      status: payload.status
    }
  }
}

export function enrichUserApiRecordForCompany(
  companyId: string,
  orgUnits: OrgUnitRecord[],
  user: UserApiRecord
): UserApiRecord {
  const assignment = resolveUserOrgUnitAssignment(companyId, user.id, user.department, orgUnits)

  return {
    ...user,
    primary_org_unit: assignment.primaryOrgUnit,
    additional_org_units: assignment.additionalOrgUnits,
    role_assignment_scope: assignment.roleAssignmentScope,
    scope_org_units: assignment.scopeOrgUnits
  }
}

export function enrichUserResponseForCompany(
  companyId: string,
  orgUnits: OrgUnitRecord[],
  payload: UserResponsePayload
): UserResponsePayload {
  return {
    ...payload,
    data: enrichUserApiRecordForCompany(companyId, orgUnits, payload.data)
  }
}

export function enrichUsersResponseForCompany(
  companyId: string,
  orgUnits: OrgUnitRecord[],
  payload: UsersResponsePayload
): UsersResponsePayload {
  return {
    ...payload,
    data: payload.data.map(user => enrichUserApiRecordForCompany(companyId, orgUnits, user))
  }
}

export { userResponseSchema, usersResponseSchema }
