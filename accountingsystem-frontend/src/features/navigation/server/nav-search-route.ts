import { NAV_SEARCH_CATALOG } from '@/features/navigation/model/nav-search.catalog'

import type { NavSearchEntry, NavSearchResponse } from '@/features/navigation/model/nav-search.types'

/**
 * Returns a relevance score for `entry` against `term`.
 * Higher is more relevant. Returns 0 when there is no match.
 */
function score(entry: NavSearchEntry, term: string): number {
  const normalizedTerm = term.toLowerCase()

  if (entry.label.toLowerCase() === normalizedTerm) return 100
  if (entry.label.toLowerCase().startsWith(normalizedTerm)) return 80
  if (entry.label.toLowerCase().includes(normalizedTerm)) return 60
  if (entry.description.toLowerCase().includes(normalizedTerm)) return 40
  if (entry.keywords.some(keyword => keyword.toLowerCase().includes(normalizedTerm))) return 30
  if (entry.section.toLowerCase().includes(normalizedTerm)) return 20

  return 0
}

function searchCatalog(query: string): NavSearchEntry[] {
  const normalizedQuery = query.trim().toLowerCase()

  if (normalizedQuery.length === 0) {
    return [...NAV_SEARCH_CATALOG]
  }

  return NAV_SEARCH_CATALOG
    .map(entry => ({ entry, score: score(entry, normalizedQuery) }))
    .filter(({ score: relevanceScore }) => relevanceScore > 0)
    .sort((left, right) => right.score - left.score)
    .map(({ entry }) => entry)
}

export function handleNavSearchRoute(request: Request): Response {
  const { searchParams } = new URL(request.url)
  const query = searchParams.get('q') ?? ''
  const results = searchCatalog(query)
  const body: NavSearchResponse = { results, query }

  return Response.json(body, {
    headers: {
      // Search results are user-session-specific (filtered by abilities client-
      // side). Keep Cache-Control private so shared caches do not mix users.
      'Cache-Control': 'private, no-store'
    }
  })
}
