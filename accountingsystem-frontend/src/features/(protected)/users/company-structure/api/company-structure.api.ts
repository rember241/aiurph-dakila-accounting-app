import { isAxiosError } from 'axios'

import {
  orgUnitApiErrorSchema,
  orgUnitFormInputSchema,
  orgUnitStatusInputSchema
} from '@/features/(protected)/users/company-structure/model/company-structure.schema'
import {
  buildOrgUnitUpstreamPayload,
  parseCompanyStructurePayload,
  parseOrgUnitMutationPayload
} from '@/features/(protected)/users/company-structure/server/company-structure-backend'
import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  CompanyStructureResponse,
  OrgUnitApiFieldErrors,
  OrgUnitFormValues,
  OrgUnitMutationResponse,
  OrgUnitStatusUpdateInput
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

export class CompanyStructureApiError extends Error {
  readonly status: number
  readonly fieldErrors: OrgUnitApiFieldErrors | null

  constructor(message: string, status: number, fieldErrors: OrgUnitApiFieldErrors | null = null) {
    super(message)
    this.name = 'CompanyStructureApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

/**
 * Normalizes axios failures into the org-unit error shape used by the page and
 * drawer components.
 */
function toApiError(err: unknown, fallback: string): CompanyStructureApiError {
  if (isAxiosError(err)) {
    const status = err.response?.status ?? 0
    const parsed = orgUnitApiErrorSchema.safeParse(err.response?.data)

    
return new CompanyStructureApiError(
      parsed.success ? (parsed.data.message ?? fallback) : fallback,
      status,
      parsed.success ? (parsed.data.errors ?? null) : null
    )
  }

  
return new CompanyStructureApiError(fallback, 0, null)
}

/**
 * Loads the org-unit register and hierarchy used by the company-structure
 * workspace.
 */
export async function fetchCompanyStructure(): Promise<CompanyStructureResponse> {
  try {
    const res = await laravelBrowserClient.get('/api/v1/org-units', {
      params: { perPage: 100, sortField: 'name', sortDir: 'asc' }
    })

    
return parseCompanyStructurePayload(res.data)
  } catch (err) {
    throw toApiError(err, 'Failed to fetch company structure.')
  }
}

/**
 * Creates a new org unit.
 */
export async function createOrgUnit(input: OrgUnitFormValues): Promise<OrgUnitMutationResponse> {
  const payload = orgUnitFormInputSchema.parse(input)

  try {
    const res = await laravelBrowserClient.post('/api/v1/org-units', buildOrgUnitUpstreamPayload(payload))

    
return parseOrgUnitMutationPayload(res.data)
  } catch (err) {
    throw toApiError(err, 'Failed to create organization unit.')
  }
}

/**
 * Updates an existing org unit.
 */
export async function updateOrgUnit(id: string, input: OrgUnitFormValues): Promise<OrgUnitMutationResponse> {
  const payload = orgUnitFormInputSchema.parse(input)

  try {
    const res = await laravelBrowserClient.put(`/api/v1/org-units/${id}`, buildOrgUnitUpstreamPayload(payload))

    
return parseOrgUnitMutationPayload(res.data)
  } catch (err) {
    throw toApiError(err, 'Failed to update organization unit.')
  }
}

/**
 * Activates or deactivates an org unit without editing the rest of the record.
 */
export async function updateOrgUnitStatus(
  id: string,
  input: OrgUnitStatusUpdateInput
): Promise<OrgUnitMutationResponse> {
  const payload = orgUnitStatusInputSchema.parse(input)

  try {
    const res = await laravelBrowserClient.patch(`/api/v1/org-units/${id}/status`, payload)

    
return parseOrgUnitMutationPayload(res.data)
  } catch (err) {
    throw toApiError(err, 'Failed to update organization unit status.')
  }
}

/**
 * Permanently deletes an org unit after backend business-rule checks pass.
 */
export async function deleteOrgUnit(id: string): Promise<{ message: string }> {
  try {
    const res = await laravelBrowserClient.delete(`/api/v1/org-units/${id}`)
    const parsed = orgUnitApiErrorSchema.safeParse(res.data)

    
return {
      message: parsed.success ? (parsed.data.message ?? 'Organization unit deleted') : 'Organization unit deleted'
    }
  } catch (err) {
    throw toApiError(err, 'Failed to delete organization unit.')
  }
}
