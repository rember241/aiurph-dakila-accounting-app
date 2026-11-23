import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import {
  companyStructureResponseSchema,
  orgUnitMutationResponseSchema
} from '@/features/(protected)/users/company-structure/model/company-structure.schema'
import { laravelTenantHeaders } from '@/lib/server-auth'

import type {
  CompanyStructureResponse,
  OrgUnitFormValues,
  OrgUnitMutationResponse,
  OrgUnitRecord,
  OrgUnitStatus
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

const BASE = process.env.LARAVEL_API_URL

const backendOrgUnitSchema = z.object({
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

const backendOrgUnitListSchema = z.object({
  data: z.array(backendOrgUnitSchema)
})

const backendOrgUnitMutationSchema = z.object({
  data: backendOrgUnitSchema,
  message: z.string()
})

function ensureBaseUrl() {
  if (!BASE) {
    throw new Error('Missing LARAVEL_API_URL')
  }

  return BASE
}

function deriveCode(name: string) {
  const normalized = name
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 24)

  return normalized || 'ORG-UNIT'
}

function mapBackendOrgUnit(unit: z.output<typeof backendOrgUnitSchema>): OrgUnitRecord {
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

function sortUnits(left: OrgUnitRecord, right: OrgUnitRecord) {
  const typeOrder = {
    head_office: 0,
    branch: 1,
    department: 2,
    team: 3
  } as const

  const diff = typeOrder[left.type] - typeOrder[right.type]

  if (diff !== 0) {
    return diff
  }

  return left.name.localeCompare(right.name)
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

      return {
        ...unit,
        children: nestedChildren,
        depth,
        directChildrenCount: nestedChildren.length
      }
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

      return stats
    },
    {
      total: 0,
      active: 0,
      inactive: 0,
      headOffice: 0,
      branches: 0,
      departments: 0,
      teams: 0
    }
  )
}

async function parseBackendError(res: Response) {
  const payload = await res.json().catch(() => null)

  return {
    message: payload?.message ?? `Request failed (${res.status})`,
    errors: payload?.errors ?? null
  }
}

export function parseCompanyStructurePayload(payload: unknown): CompanyStructureResponse {
  const parsed = backendOrgUnitListSchema.parse(payload)
  const units = parsed.data.map(mapBackendOrgUnit).sort(sortUnits)

  return companyStructureResponseSchema.parse({
    data: {
      units,
      hierarchy: buildHierarchy(units),
      stats: buildStats(units)
    }
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

export async function fetchBackendOrgUnits(req: Request): Promise<OrgUnitRecord[]> {
  const base = ensureBaseUrl()

  const res = await fetch(`${base}/api/v1/org-units?perPage=100&sortField=name&sortDir=asc`, {
    method: 'GET',
    headers: laravelTenantHeaders(req, { Accept: 'application/json' }),
    cache: 'no-store'
  })

  if (!res.ok) {
    const error = await parseBackendError(res)

    throw new Response(JSON.stringify(error), {
      status: res.status,
      headers: { 'Content-Type': 'application/json' }
    })
  }

  const payload = await res.json().catch(() => null)
  const parsed = backendOrgUnitListSchema.parse(payload)

  return parsed.data.map(mapBackendOrgUnit).sort(sortUnits)
}

export async function fetchBackendCompanyStructure(req: Request): Promise<CompanyStructureResponse> {
  const units = await fetchBackendOrgUnits(req)

  return companyStructureResponseSchema.parse({
    data: {
      units,
      hierarchy: buildHierarchy(units),
      stats: buildStats(units)
    }
  })
}

export async function createBackendOrgUnit(req: Request, input: OrgUnitFormValues): Promise<OrgUnitMutationResponse> {
  const base = ensureBaseUrl()

  const res = await fetch(`${base}/api/v1/org-units`, {
    method: 'POST',
    headers: laravelTenantHeaders(req, {
      Accept: 'application/json',
      'Content-Type': 'application/json'
    }),
    body: JSON.stringify({
      name: input.name,
      code: input.code,
      type: input.type,
      parent_org_unit_id: input.parentId,
      location: input.location || null,
      description: input.description || null
    })
  })

  if (!res.ok) {
    const error = await parseBackendError(res)

    throw new Response(JSON.stringify(error), {
      status: res.status,
      headers: { 'Content-Type': 'application/json' }
    })
  }

  const payload = await res.json().catch(() => null)
  const parsed = backendOrgUnitMutationSchema.parse(payload)

  return orgUnitMutationResponseSchema.parse({
    data: mapBackendOrgUnit(parsed.data),
    message: parsed.message
  })
}

export async function updateBackendOrgUnit(
  req: Request,
  id: string,
  input: OrgUnitFormValues
): Promise<OrgUnitMutationResponse> {
  const base = ensureBaseUrl()

  const res = await fetch(`${base}/api/v1/org-units/${id}`, {
    method: 'PUT',
    headers: laravelTenantHeaders(req, {
      Accept: 'application/json',
      'Content-Type': 'application/json'
    }),
    body: JSON.stringify({
      name: input.name,
      code: input.code,
      type: input.type,
      parent_org_unit_id: input.parentId,
      location: input.location || null,
      description: input.description || null
    })
  })

  if (!res.ok) {
    const error = await parseBackendError(res)

    throw new Response(JSON.stringify(error), {
      status: res.status,
      headers: { 'Content-Type': 'application/json' }
    })
  }

  const payload = await res.json().catch(() => null)
  const parsed = backendOrgUnitMutationSchema.parse(payload)

  return orgUnitMutationResponseSchema.parse({
    data: mapBackendOrgUnit(parsed.data),
    message: parsed.message
  })
}

export async function updateBackendOrgUnitStatus(
  req: Request,
  id: string,
  status: OrgUnitStatus
): Promise<OrgUnitMutationResponse> {
  const base = ensureBaseUrl()

  const res = await fetch(`${base}/api/v1/org-units/${id}/status`, {
    method: 'PATCH',
    headers: laravelTenantHeaders(req, {
      Accept: 'application/json',
      'Content-Type': 'application/json'
    }),
    body: JSON.stringify({ status })
  })

  if (!res.ok) {
    const error = await parseBackendError(res)

    throw new Response(JSON.stringify(error), {
      status: res.status,
      headers: { 'Content-Type': 'application/json' }
    })
  }

  const payload = await res.json().catch(() => null)
  const parsed = backendOrgUnitMutationSchema.parse(payload)

  return orgUnitMutationResponseSchema.parse({
    data: mapBackendOrgUnit(parsed.data),
    message: parsed.message
  })
}

export async function deleteBackendOrgUnit(req: Request, id: string): Promise<{ message: string }> {
  const base = ensureBaseUrl()

  const res = await fetch(`${base}/api/v1/org-units/${id}`, {
    method: 'DELETE',
    headers: laravelTenantHeaders(req, {
      Accept: 'application/json'
    })
  })

  if (!res.ok) {
    const error = await parseBackendError(res)

    throw new Response(JSON.stringify(error), {
      status: res.status,
      headers: { 'Content-Type': 'application/json' }
    })
  }

  const payload = await res.json().catch(() => null)
  const parsed = z.object({ message: z.string() }).parse(payload)

  return parsed
}
