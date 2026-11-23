export const TENANT_STATUSES = ['active', 'inactive'] as const
export const TENANT_SORT_FIELDS = ['name', 'slug', 'status', 'timezone', 'created_at', 'updated_at'] as const
export const TENANT_SORT_DIRECTIONS = ['asc', 'desc'] as const

export type TenantStatus = (typeof TENANT_STATUSES)[number]
export type TenantSortField = (typeof TENANT_SORT_FIELDS)[number]
export type TenantSortDirection = (typeof TENANT_SORT_DIRECTIONS)[number]

export type TenantOwner = {
  id: number
  name: string
  email: string
}

export type TenantRoleSummary = {
  id: string
  tenant_id: string | null
  key: string
  name: string
  status: string
}

export type TenantOwnerMembership = {
  id: string
  user_id: number
  status: string
  joined_at: string | null
  roles: TenantRoleSummary[]
}

export type TenantRecord = {
  id: string
  name: string
  slug: string
  status: TenantStatus
  timezone: string
  owner_user_id: string | null
  owner: TenantOwner | null
  owner_membership: TenantOwnerMembership | null
  created_at: string
  updated_at: string
}

export type TenantQuery = {
  q?: string
  status?: TenantStatus | ''
  page?: number // 0-based in the frontend
  perPage?: number
  sortField?: TenantSortField
  sortDir?: TenantSortDirection
}

export type TenantsResponse = {
  data: TenantRecord[]
  page: number
  perPage: number
  total: number
}

export type CreateTenantFormValues = {
  name: string
  slug: string
  timezone: string
}

export type CreateTenantPayload = {
  name: string
  slug?: string
  timezone: string
}

export type CreateTenantResponse = {
  data: TenantRecord
  message: string
}

export type TenantApiFieldErrors = Record<string, string[] | undefined>
