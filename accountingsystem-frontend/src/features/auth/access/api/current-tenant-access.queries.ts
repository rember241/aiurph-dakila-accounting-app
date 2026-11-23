import { useQuery } from '@tanstack/react-query'

import { fetchCurrentTenantAccess } from '@/features/auth/access/api/current-tenant-access.api'
import { CURRENT_TENANT_COOKIE } from '@/features/(protected)/users/tenants/model/current-tenant.schema'

import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

type CurrentTenantAccessQueryOptions = {
  initialData?: CurrentTenantAccessState
}

/**
 * Reads the current_tenant_id cookie on the client. We gate the access query
 * on this so we don't fire /api/v1/me/access during onboarding (no tenant yet)
 * or before Login.tsx / CompanyOnboardingPage.tsx has bootstrapped the cookie
 * — both cases would produce a 404 that ends up as a console error.
 */
function hasCurrentTenantCookie() {
  if (typeof document === 'undefined') return false

  return document.cookie
    .split('; ')
    .some(entry => entry.startsWith(`${CURRENT_TENANT_COOKIE}=`) && entry.length > CURRENT_TENANT_COOKIE.length + 1)
}

/**
 * Loads the current company access snapshot for client-side permission gates.
 *
 * The protected layout usually seeds this with SSR data first, then React Query
 * keeps it warm for buttons, menus, and secondary screens.
 */
export function useCurrentTenantAccessQuery(options: CurrentTenantAccessQueryOptions = {}) {
  return useQuery({
    queryKey: ['auth', 'tenant-access'],
    queryFn: fetchCurrentTenantAccess,
    initialData: options.initialData,

    // Tell React Query when the SSR-provided initialData was last fetched.
    // Without this, React Query defaults initialDataUpdatedAt to 0 (epoch
    // start), which makes the data appear immediately stale regardless of
    // staleTime. That triggers an instant background refetch on mount, which
    // toggles isFetching in CurrentTenantAccessProvider's useMemo deps,
    // propagates a new context value to every consumer, causes VerticalMenu
    // to re-render, and re-registers its <Link href="/home"> with Next.js's
    // IntersectionObserver — producing a spurious GET /home prefetch in the
    // terminal every time the protected layout mounts.
    //
    // We only treat it as fresh when the tenant is actually present
    // (non-null tenantId confirms a real SSR response, not the empty-state
    // fallback). For the empty fallback, leaving initialDataUpdatedAt=0 lets
    // React Query refetch immediately once the query is enabled.
    initialDataUpdatedAt: options.initialData?.data.tenantId != null ? Date.now() : undefined,
    placeholderData: previous => previous,

    // 5-minute staleTime: permissions rarely change mid-session. A short staleTime
    // (e.g. 10 s) combined with refetchOnWindowFocus causes a backend hit every
    // time the developer (or user) switches back to the tab, which spams
    // /api/v1/me/access. Explicit false disables window-focus refetches entirely
    // for this query so background noise is eliminated.
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    retry: false,

    // Only hit /api/v1/me/access when the tenant cookie is present. Otherwise
    // Laravel's company.context middleware aborts with 404 and the provider
    // would re-render with the empty initialState anyway.
    enabled: hasCurrentTenantCookie()
  })
}
