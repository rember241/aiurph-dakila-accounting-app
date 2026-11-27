import { redirect } from 'next/navigation'

import type { ChildrenType } from '@core/types'

import Providers from '@components/Providers'
import BlankLayout from '@layouts/BlankLayout'

import { getSystemMode } from '@core/utils/serverHelpers'
import { getOnboardingLayoutRedirect } from '@/lib/server-onboarding'
import { getServerSession } from '@/lib/server-session'

const Layout = async (props: ChildrenType) => {
  const { children } = props
  const session = await getServerSession()

  if (!session) {
    redirect('/login')
  }

  const redirectTarget = getOnboardingLayoutRedirect(session.user)

  if (redirectTarget) {
    redirect(redirectTarget)
  }

  const direction = 'ltr'
  const systemMode = await getSystemMode()

  return (

    <Providers direction={direction} sessionWindow={session.sessionWindow} withTenantAccess={false}>
      <BlankLayout systemMode={systemMode}>{children}</BlankLayout>
    </Providers>
  )
}

export default Layout
