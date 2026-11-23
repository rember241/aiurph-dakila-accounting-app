import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { searchNavigation } from '@/features/navigation/api/nav-search.api'

import type { NavSearchResponse } from '@/features/navigation/model/nav-search.types'

export function getNavSearchQueryKey(query: string) {
  return ['nav-search', query.trim()] as const
}

/**
 * React Query hook for navigation search.
 *
 * `debouncedQuery` should be pre-debounced by the caller — this hook fires a
 * network request on every change to `debouncedQuery`.  Pass an empty string
 * to show the full catalog (used for the "empty" / initial state).
 */
export function useNavSearchQuery(debouncedQuery: string) {
  return useQuery<NavSearchResponse>({
    queryKey: getNavSearchQueryKey(debouncedQuery),
    queryFn: () => searchNavigation(debouncedQuery),

    // Keep previous results visible while the new query is loading so the
    // list doesn't flash blank between keystrokes.
    placeholderData: keepPreviousData,
    staleTime: 30_000,

    // Always enabled — the route returns the full catalog for an empty query.
    enabled: true
  })
}
