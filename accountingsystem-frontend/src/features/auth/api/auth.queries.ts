import { isAxiosError } from 'axios'

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { login, logout, me } from './auth.api'

import type { LoginResponse, LogoutResponse, MeResponse } from '@/features/auth/model/auth.schema'

export const AUTH_QUERY_KEYS = {
  me: ['auth', 'me'] as const
}

/**
 * Keeps the current session user available across the app.
 *
 * Returning `{ user: null }` for 401 responses lets protected pages redirect
 * cleanly without treating "logged out" as a hard query failure.
 */
export function useMeQuery() {
  return useQuery<MeResponse>({
    queryKey: AUTH_QUERY_KEYS.me,
    queryFn: async () => {
      try {
        return await me()
      } catch (error) {
        if (isAxiosError(error) && error.response?.status === 401) {
          return { user: null }
        }

        throw error
      }
    },
    staleTime: 1000 * 60 * 5,
    retry: false
  })
}

/**
 * Writes the login session and seeds the `me` cache immediately so protected
 * screens do not wait for an extra round-trip after the form succeeds.
 */
export function useLoginMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: unknown) => login(input),
    onSuccess: (data: LoginResponse) => {
      // Seed the me cache immediately so consumers see the logged-in user
      // without waiting for a network round-trip.
      queryClient.setQueryData(AUTH_QUERY_KEYS.me, data)

      // REMOVE (do not invalidate) the tenant-access cache. A broad
      // invalidateQueries(['auth']) would mark ['auth','tenant-access'] as
      // invalid, then trigger an immediate background refetch while the login
      // page's CurrentTenantAccessProvider is still mounted — and a second
      // refetch the moment the protected layout mounts. Instead, removing it
      // lets the SSR-provided initialData on /home act as freshly fetched
      // data; React Query records dataUpdatedAt=now and won't refetch within
      // the 5-minute staleTime.
      queryClient.removeQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

/**
 * Ends the current session.
 *
 * We intentionally avoid aggressive cache clearing here because callers follow
 * logout with a hard navigation, and clearing everything too early causes noisy
 * refetches while the old tree is still mounted.
 */
export function useLogoutMutation() {
  // Intentionally no onSuccess cache manipulation.
  //
  // Callers follow this mutation with a hard navigation
  // (`window.location.replace('/login')`) which tears down the JS
  // context and rebuilds the React Query cache from scratch on the next
  // page load. Clearing the cache here while the tree is still mounted
  // — especially on the dashboard, where the sidebar holds a visible
  // <Link href="/home"> — triggers a cascade of observer refetches and
  // RSC prefetches that race the navigation. Those prefetches land on
  // middleware with the session cookie already gone and flood the dev
  // server with `GET /home 307` log lines.
  return useMutation<LogoutResponse, Error, void>({ mutationFn: logout })
}
