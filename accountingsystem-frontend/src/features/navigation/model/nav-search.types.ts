import type { z } from 'zod'

import type { navSearchSectionSchema } from '@/features/navigation/model/nav-search.schema'

// ─── Nav Search Types ─────────────────────────────────────────────────────────

/** Derived from the Zod enum so the schema and type stay in sync. */
export type NavSearchSection = z.output<typeof navSearchSectionSchema>

export type NavSearchEntry = {

  /** Unique stable ID — used as React key and query result identifier. */
  id: string

  /** Primary label shown in the result row. */
  label: string

  /** Short sentence describing what this page does — shown below the label. */
  description: string

  /** Next.js href to navigate to. */
  href: string

  /** Boxicons class name (without leading "bx "). */
  icon: string

  /** Navigation section / module the page belongs to. */
  section: NavSearchSection

  /**
   * Flat list of words or phrases used for matching in addition to label and
   * description. Allows aliases (e.g. "AR" matching "Accounts Receivable").
   */
  keywords: string[]

  /** APP_ABILITIES values required to access this page. Empty → always visible. */
  requiredAbilities: string[]
}

export type NavSearchResponse = {
  results: NavSearchEntry[]
  query: string
}
