import { redirect } from 'next/navigation'

import type { ChildrenType } from '@core/types'

import Providers from '@components/Providers'
import BlankLayout from '@layouts/BlankLayout'

import { getSystemMode } from '@core/utils/serverHelpers'
import { getOnboardingLayoutRedirect } from '@/lib/server-onboarding'
import { getServerAuthenticatedUser } from '@/lib/server-session'

const Layout = async (props: ChildrenType) => {
  const { children } = props
  const user = await getServerAuthenticatedUser()

  if (!user) {
    redirect('/login')
  }

  const redirectTarget = getOnboardingLayoutRedirect(user)

  if (redirectTarget) {
    redirect(redirectTarget)
  }

  const direction = 'ltr'
  const systemMode = await getSystemMode()

  return (

    // withTenantAccess={false} — company has not been created yet at this
    // stage, so there is no valid tenant context. Suppresses the client-side
    // useCurrentTenantAccessQuery to prevent 404s from /api/v1/me/access
    // (which requires a company context) and spurious refetches if a stale
    // current_tenant_id cookie is present from a previous session.
    <Providers direction={direction} withTenantAccess={false}>
      <BlankLayout systemMode={systemMode}>{children}</BlankLayout>
    </Providers>
  )
}

export default Layout
