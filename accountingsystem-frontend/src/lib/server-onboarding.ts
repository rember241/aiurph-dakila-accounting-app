import type { AuthMeUser } from '@/features/auth/model/auth.schema'
import { getPostOnboardingRedirect, requiresCompanyOnboarding } from '@/features/auth/model/auth.schema'

/**
 * Decides whether an authenticated user should be diverted into company setup
 * before the protected layout renders.
 */
export function getProtectedLayoutOnboardingRedirect(user: AuthMeUser) {
  if (requiresCompanyOnboarding(user)) {
    return '/onboarding/company'
  }

  return null
}

/**
 * Mirrors the protected-layout rule for the onboarding route group.
 *
 * Once setup is complete we redirect back into the main app so the onboarding
 * screens cannot be revisited by accident.
 */
export function getOnboardingLayoutRedirect(user: AuthMeUser) {
  if (requiresCompanyOnboarding(user)) {
    return null
  }

  return getPostOnboardingRedirect(user)
}
