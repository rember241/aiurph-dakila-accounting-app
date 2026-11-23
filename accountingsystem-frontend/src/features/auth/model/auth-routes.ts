import { APP_ABILITIES, hasCurrentTenantAbility } from '@/features/auth/access/model/access.schema'

import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

// Use /home directly to avoid the next.config.ts permanent redirect hop
// (/ → /home) which causes an extra server RSC round-trip and an extra
// server-side /api/v1/me/access call on every post-login navigation.
export const DEFAULT_PROTECTED_REDIRECT = '/home'
export const FALLBACK_PROTECTED_REDIRECT = '/home'

type ProtectedLandingRule = {
  ability: string
  href: string
}

const PROTECTED_LANDING_RULES: ProtectedLandingRule[] = [
  { ability: APP_ABILITIES.accountingRead, href: '/home' },
  { ability: APP_ABILITIES.periodRead, href: '/accounting/setup/periods' },
  { ability: APP_ABILITIES.journalRead, href: '/accounting/transactions/journal-entries' },
  { ability: APP_ABILITIES.rolesRead, href: '/users/roles' },
]

export function normalizeProtectedRedirectTarget(redirectTo: string | null | undefined) {
  if (!redirectTo || redirectTo.trim() === '') {
    return DEFAULT_PROTECTED_REDIRECT
  }

  // Previously mapped '/home' → '/' (when DEFAULT_PROTECTED_REDIRECT was '/').
  // Now that DEFAULT_PROTECTED_REDIRECT IS '/home', pass it through as-is.
  return redirectTo
}

export function resolveProtectedLandingRoute(access: CurrentTenantAccessState['data']) {
  const matchedRoute = PROTECTED_LANDING_RULES.find(({ ability }) => hasCurrentTenantAbility(access, ability))

  return matchedRoute?.href ?? FALLBACK_PROTECTED_REDIRECT
}
