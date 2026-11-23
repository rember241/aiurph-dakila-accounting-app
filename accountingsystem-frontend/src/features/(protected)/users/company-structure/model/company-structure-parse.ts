/**
 * Pure parse / transform utilities for the company-structure API payload.
 *
 * This file must NOT import from any server-only module (next/headers,
 * server-auth, etc.) so it can be safely bundled into client components.
 * Both the client-side API (company-structure.api.ts) and the server-side
 * backend proxy (company-structure-backend.ts) import from here.
 */
import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import {
  companyStructureResponseSchema,
  orgUnitMutationResponseSchema
} from '@/features/(protected)/users/company-structure/model/company-structure.schema'

import type {
  CompanyStructureResponse,
  OrgUnitFormValues,
  OrgUnitMutationResponse,
  OrgUnitRecord
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

// ── Backend Zod schemas ────────────────────────────────────────────────────────

export const backendOrgUnitSchema = z.object({
  id: zUuid(),
  company_id: zUuid(),
  parent_org_unit_id: zUuid().nullable(),
  type: z.enum(['head_office', 'branch', 'department', 'team']),
  name: z.string().min(1),
  code: z.string().min(1).optional(),
  location: z.string().nullable().optional(),
  description: z.string().nullable().optional(),
  status: z.enum(['active', 'inactive']),
  is_root: z.boolean().optional().default(false),
  members_count: z.number().int().nonnegative().optional().default(0),
  linked_roles_count: z.number().int().nonnegative().optional().default(0),
  created_at: z.string(),
  updated_at: z.string()
})

export const backendOrgUnitListSchema = z.object({
  data: z.array(backendOrgUnitSchema)
})

export const backendOrgUnitMutationSchema = z.object({
  data: backendOrgUnitSchema,
  message: z.string()
})

// ── Internal helpers ───────────────────────────────────────────────────────────

function deriveCode(name: string) {
  const normalized = name
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 24)

  return normalized || 'ORG-UNIT'
}

export function mapBackendOrgUnit(unit: z.output<typeof backendOrgUnitSchema>): OrgUnitRecord {
  return {
    id: unit.id,
    companyId: unit.company_id,
    parentId: unit.parent_org_unit_id,
    name: unit.name,
    code: unit.code?.trim() || deriveCode(unit.name),
    type: unit.type,
    status: unit.status,
    isRoot: unit.is_root,
    membersCount: unit.members_count,
    linkedRolesCount: unit.linked_roles_count,
    location: unit.location ?? null,
    description: unit.description ?? null,
    createdAt: unit.created_at,
    updatedAt: unit.updated_at
  }
}

export function sortUnits(left: OrgUnitRecord, right: OrgUnitRecord) {
  const typeOrder = {
    head_office: 0,
    branch: 1,
    department: 2,
    team: 3
  } as const

  const diff = typeOrder[left.type] - typeOrder[right.type]

  return diff !== 0 ? diff : left.name.localeCompare(right.name)
}

function buildHierarchy(units: OrgUnitRecord[]): CompanyStructureResponse['data']['hierarchy'] {
  const childrenByParent = new Map<string | null, OrgUnitRecord[]>()

  for (const unit of units) {
    const bucket = childrenByParent.get(unit.parentId) ?? []

    bucket.push(unit)
    childrenByParent.set(unit.parentId, bucket)
  }

  for (const bucket of childrenByParent.values()) {
    bucket.sort(sortUnits)
  }

  const buildNodes = (parentId: string | null, depth: number): CompanyStructureResponse['data']['hierarchy'] => {
    const children = childrenByParent.get(parentId) ?? []

    return children.map(unit => {
      const nestedChildren = buildNodes(unit.id, depth + 1)

      return { ...unit, children: nestedChildren, depth, directChildrenCount: nestedChildren.length }
    })
  }

  return buildNodes(null, 0)
}

function buildStats(units: OrgUnitRecord[]): CompanyStructureResponse['data']['stats'] {
  return units.reduce(
    (stats, unit) => {
      stats.total += 1
      stats[unit.status === 'active' ? 'active' : 'inactive'] += 1

      switch (unit.type) {
        case 'head_office': stats.headOffice += 1; break
        case 'branch':      stats.branches   += 1; break
        case 'department':  stats.departments += 1; break
        case 'team':        stats.teams       += 1; break
      }

      return stats
    },
    { total: 0, active: 0, inactive: 0, headOffice: 0, branches: 0, departments: 0, teams: 0 }
  )
}

// ── Exported parse helpers (used by both client API and server backend) ────────

/** Build the full structure response from an already-mapped unit list. */
export function buildCompanyStructureFromUnits(units: OrgUnitRecord[]): CompanyStructureResponse {
  return companyStructureResponseSchema.parse({
    data: { units, hierarchy: buildHierarchy(units), stats: buildStats(units) }
  })
}

export function parseCompanyStructurePayload(payload: unknown): CompanyStructureResponse {
  const parsed = backendOrgUnitListSchema.parse(payload)
  const units = parsed.data.map(mapBackendOrgUnit).sort(sortUnits)

  return companyStructureResponseSchema.parse({
    data: { units, hierarchy: buildHierarchy(units), stats: buildStats(units) }
  })
}

export function parseOrgUnitMutationPayload(payload: unknown): OrgUnitMutationResponse {
  const parsed = backendOrgUnitMutationSchema.parse(payload)

  return orgUnitMutationResponseSchema.parse({
    data: mapBackendOrgUnit(parsed.data),
    message: parsed.message
  })
}

export function buildOrgUnitUpstreamPayload(input: OrgUnitFormValues) {
  return {
    name: input.name,
    code: input.code,
    type: input.type,
    parent_org_unit_id: input.parentId,
    location: input.location || null,
    description: input.description || null
  }
}
