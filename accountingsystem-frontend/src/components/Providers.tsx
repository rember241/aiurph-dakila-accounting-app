// Type Imports
import type { ChildrenType, Direction } from '@core/types'

// Context Imports
import { VerticalNavProvider } from '@menu/contexts/verticalNavContext'
import { SettingsProvider } from '@core/contexts/settingsContext'
import ThemeProvider from '@components/theme'
import CurrentTenantAccessProvider from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { createEmptyCurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

// Util Imports
import { getMode, getSettingsFromCookie, getSystemMode } from '@core/utils/serverHelpers'

type Props = ChildrenType & {
  direction: Direction
  initialTenantAccess?: ReturnType<typeof createEmptyCurrentTenantAccessState>

  /**
   * Mount CurrentTenantAccessProvider and its client-side query.
   * Set to false on public routes (login, onboarding) where there is no
   * authenticated tenant context — omitting the provider prevents
   * useCurrentTenantAccessQuery from running with a stale/residual
   * current_tenant_id cookie and firing spurious /api/v1/me/access requests
   * before the user has actually logged in.
   */
  withTenantAccess?: boolean
}

/**
 * Central app wrapper for every route group.
 *
 * This keeps layout state, theme state, and company access state wired in one
 * place so route files can stay focused on redirect and permission decisions.
 */
const Providers = async (props: Props) => {
  // Props
  const {
    children,
    direction,
    initialTenantAccess = createEmptyCurrentTenantAccessState(),
    withTenantAccess = true
  } = props

  // Vars
  const mode = await getMode()
  const settingsCookie = await getSettingsFromCookie()
  const systemMode = await getSystemMode()

  return (
    <VerticalNavProvider>
      <SettingsProvider settingsCookie={settingsCookie} mode={mode}>
        <ThemeProvider direction={direction} systemMode={systemMode}>
          {withTenantAccess ? (
            <CurrentTenantAccessProvider initialState={initialTenantAccess}>{children}</CurrentTenantAccessProvider>
          ) : (
            children
          )}
        </ThemeProvider>
      </SettingsProvider>
    </VerticalNavProvider>
  )
}

export default Providers
