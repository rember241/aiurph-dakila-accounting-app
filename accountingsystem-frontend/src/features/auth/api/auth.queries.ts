import { isAxiosError } from 'axios'

import { useMutation, useQuery } from '@tanstack/react-query'

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

export function useLoginMutation() {
  return useMutation<LoginResponse, Error, unknown>({ mutationFn: login })
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
