import { navSearchResponseSchema } from '@/features/navigation/model/nav-search.schema'

import type { NavSearchResponse } from '@/features/navigation/model/nav-search.types'

/**
 * Fetch navigation search results from the internal Next.js API route.
 *
 * This is intentionally NOT routed through `laravelBrowserClient` — the search
 * catalog is a frontend concern and lives in the Next.js layer.
 */
export async function searchNavigation(query: string): Promise<NavSearchResponse> {
  const url = `/api/nav-search?q=${encodeURIComponent(query.trim())}`

  const res = await fetch(url, {
    method: 'GET',
    headers: { Accept: 'application/json' },

    // Always fetch fresh — debounce in the hook handles request rate.
    cache: 'no-store'
  })

  if (!res.ok) {
    throw new Error(`Navigation search failed (${res.status})`)
  }

  const data = await res.json()

  return navSearchResponseSchema.parse(data)
}
