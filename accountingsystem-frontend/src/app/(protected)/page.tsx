import { redirect } from 'next/navigation'

import { resolveProtectedLandingRoute } from '@/features/auth/model/auth-routes'
import { getServerCurrentTenantAccess } from '@/lib/server-access'

export default async function ProtectedLandingPage() {
  const access = await getServerCurrentTenantAccess()

  redirect(resolveProtectedLandingRoute(access.data))
}
