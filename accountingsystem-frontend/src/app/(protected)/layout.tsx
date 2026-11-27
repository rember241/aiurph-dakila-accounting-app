import { redirect } from 'next/navigation'

// MUI Imports
import Button from '@mui/material/Button'

// Type Imports
import type { ChildrenType } from '@core/types'

// Layout Imports
import LayoutWrapper from '@layouts/LayoutWrapper'
import VerticalLayout from '@layouts/VerticalLayout'
import HorizontalLayout from '@layouts/HorizontalLayout'

// Component Imports
import Providers from '@components/Providers'
import Navigation from '@components/layout/vertical/Navigation'
import Header from '@components/layout/horizontal/Header'
import Navbar from '@components/layout/vertical/Navbar'
import VerticalFooter from '@components/layout/vertical/Footer'
import HorizontalFooter from '@components/layout/horizontal/Footer'
import ScrollToTop from '@core/components/scroll-to-top'
import { getServerSession } from '@/lib/server-session'
import { getServerCurrentTenantAccess } from '@/lib/server-access'
import { getProtectedLayoutOnboardingRedirect } from '@/lib/server-onboarding'

// Util Imports
import { getMode, getSystemMode } from '@core/utils/serverHelpers'

/**
 * Shared shell for authenticated ERP pages.
 *
 * The protected route group does three jobs before it renders any page content:
 * confirm the Laravel session still exists, bounce users into onboarding when
 * company setup is incomplete, and hydrate the initial access snapshot used by
 * client-side permission checks.
 */
const Layout = async (props: ChildrenType) => {
  const { children } = props

  const direction = 'ltr'
  const mode = await getMode()
  const systemMode = await getSystemMode()

  const session = await getServerSession()

  if (!session) {
    redirect('/login')
  }

  const onboardingRedirect = getProtectedLayoutOnboardingRedirect(session.user)

  if (onboardingRedirect) {
    // Keep onboarding enforcement here so feature pages do not need to worry
    // about incomplete company setup individually.
    redirect(onboardingRedirect)
  }

  const tenantAccess = await getServerCurrentTenantAccess()

  return (
    <Providers direction={direction} sessionWindow={session.sessionWindow} initialTenantAccess={tenantAccess}>
      <LayoutWrapper
        systemMode={systemMode}
        verticalLayout={
          <VerticalLayout navigation={<Navigation mode={mode} />} navbar={<Navbar />} footer={<VerticalFooter />}>
            {children}
          </VerticalLayout>
        }
        horizontalLayout={
          <HorizontalLayout header={<Header />} footer={<HorizontalFooter />}>
            {children}
          </HorizontalLayout>
        }
      />
      <ScrollToTop className='mui-fixed'>
        <Button variant='contained' className='is-10 bs-10 rounded-full p-0 min-is-0 flex items-center justify-center'>
          <i className='bx-up-arrow-alt' />
        </Button>
      </ScrollToTop>
    </Providers>
  )
}

export default Layout
