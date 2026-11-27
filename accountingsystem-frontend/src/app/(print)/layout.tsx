import { redirect } from 'next/navigation'

import type { ChildrenType } from '@core/types'

import Providers from '@components/Providers'
import BlankLayout from '@layouts/BlankLayout'

import { getSystemMode } from '@core/utils/serverHelpers'
import { getProtectedLayoutOnboardingRedirect } from '@/lib/server-onboarding'
import { getServerCurrentTenantAccess } from '@/lib/server-access'
import { getServerSession } from '@/lib/server-session'

const Layout = async (props: ChildrenType) => {
  const { children } = props
  const session = await getServerSession()

  if (!session) {
    redirect('/login')
  }

  const redirectTarget = getProtectedLayoutOnboardingRedirect(session.user)

  if (redirectTarget) {
    redirect(redirectTarget)
  }

  const direction = 'ltr'
  const systemMode = await getSystemMode()
  const tenantAccess = await getServerCurrentTenantAccess()

  return (
    <Providers direction={direction} sessionWindow={session.sessionWindow} initialTenantAccess={tenantAccess}>
      <BlankLayout systemMode={systemMode}>{children}</BlankLayout>
    </Providers>
  )
}

export default Layout
