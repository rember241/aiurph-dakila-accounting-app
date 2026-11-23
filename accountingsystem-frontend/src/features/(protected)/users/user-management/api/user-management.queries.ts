import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createUser,
  deleteUser,
  fetchUsers,
  fetchUserStats,
  transitionUserStatus,
  updateUser
} from '@/features/(protected)/users/user-management/api/user-management.api'

import type {
  UserQuery,
  UserRecord,
  UserStatus
} from '@/features/(protected)/users/user-management/model/user-management.types'

export function useUsersQuery(query: UserQuery, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['users', query],
    queryFn: () => fetchUsers(query),
    enabled: options?.enabled ?? true,
    placeholderData: previous => previous,
    staleTime: 10_000
  })
}

export function useUserStatsQuery(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['users', 'stats'],
    queryFn: fetchUserStats,
    enabled: options?.enabled ?? true,
    staleTime: 10_000
  })
}

export function useCreateUserMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createUser,
    onSuccess: user => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
      queryClient.setQueryData<UserRecord[]>(['users'], previous => previous ?? [user])
    }
  })
}

export function useUpdateUserMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ userId, values }: { userId: string; values: unknown }) => updateUser(userId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

export function useTransitionUserStatusMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ userId, status }: { userId: string; status: UserStatus }) => transitionUserStatus(userId, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}

export function useDeleteUserMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['users', 'stats'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'tenant-access'] })
    }
  })
}
