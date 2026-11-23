'use client'

import type { ReactNode } from 'react'
import { createContext, useContext, useMemo } from 'react'

import { useCurrentTenantAccessQuery } from '@/features/auth/access/api/current-tenant-access.queries'
import {
  createEmptyCurrentTenantAccessState,
  hasAllCurrentTenantAbilities,
  hasAnyCurrentTenantAbility,
  hasCurrentTenantAbility
} from '@/features/auth/access/model/access.schema'

import type { AppAbility, CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

type CurrentTenantAccessContextValue = CurrentTenantAccessState['data'] & {
  isLoading: boolean
  isFetching: boolean
  can: (ability: AppAbility | string) => boolean
  canAny: (abilities: readonly (AppAbility | string)[]) => boolean
  canAll: (abilities: readonly (AppAbility | string)[]) => boolean
  state: CurrentTenantAccessState
}

const CurrentTenantAccessContext = createContext<CurrentTenantAccessContextValue | null>(null)

type Props = {
  initialState?: CurrentTenantAccessState
  children: ReactNode
}

/**
 * Small client-side wrapper around the current company access snapshot.
 *
 * Pages use the `can / canAny / canAll` helpers from this context instead of
 * re-implementing ability checks in every component.
 */
export default function CurrentTenantAccessProvider({
  initialState = createEmptyCurrentTenantAccessState(),
  children
}: Props) {
  const query = useCurrentTenantAccessQuery({ initialData: initialState })
  const state = query.data ?? initialState

  const value = useMemo<CurrentTenantAccessContextValue>(
    () => ({
      ...state.data,
      isLoading: query.isLoading,
      isFetching: query.isFetching,
      can: ability => hasCurrentTenantAbility(state.data, ability),
      canAny: abilities => hasAnyCurrentTenantAbility(state.data, abilities),
      canAll: abilities => hasAllCurrentTenantAbilities(state.data, abilities),
      state
    }),
    [query.isFetching, query.isLoading, state]
  )

  return <CurrentTenantAccessContext.Provider value={value}>{children}</CurrentTenantAccessContext.Provider>
}

/**
 * Reads the current company access context.
 */
export function useCurrentTenantAccess() {
  const context = useContext(CurrentTenantAccessContext)

  if (!context) {
    throw new Error('useCurrentTenantAccess must be used within CurrentTenantAccessProvider.')
  }

  return context
}
