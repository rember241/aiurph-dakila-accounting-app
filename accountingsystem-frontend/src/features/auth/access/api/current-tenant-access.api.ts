import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import { currentTenantAccessStateSchema } from '@/features/auth/access/model/access.schema'

import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

export class CurrentTenantAccessApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'CurrentTenantAccessApiError'
    this.status = status
  }
}

export async function fetchCurrentTenantAccess(): Promise<CurrentTenantAccessState> {
  // GET requests do not require CSRF validation — do NOT call bootstrapLaravelCsrf() here.
  const res = await laravelBrowserClient.get('/api/v1/me/access')

  return currentTenantAccessStateSchema.parse(res.data)
}
