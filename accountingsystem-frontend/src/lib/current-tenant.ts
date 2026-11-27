import { redirect } from 'next/navigation'

import { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'
import { getServerCurrentTenantAccess } from '@/lib/server-access'

export async function getServerCurrentTenantId() {
  const access = await getServerCurrentTenantAccess()

  return access.data.tenantId
}

export async function requireServerCurrentTenantId(redirectTo = DEFAULT_PROTECTED_REDIRECT) {
  const currentTenantId = await getServerCurrentTenantId()

  if (!currentTenantId) {
    redirect(redirectTo)
  }

  return currentTenantId
}
