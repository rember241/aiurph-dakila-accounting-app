import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  deleteUserAvatar,
  deleteCompanyLogo,
  fetchCompanySettings,
  fetchUserSettings,
  uploadUserAvatar,
  uploadCompanyLogo,
  updateCompanySettings,
  updateUserSettings
} from '@/features/(protected)/settings/api/settings.api'

import type {
  CompanySettingsFormValues,
  CompanySettingsRecord,
  UserSettingsFormValues,
  UserSettingsRecord
} from '@/features/(protected)/settings/model/settings.schema'

/**
 * Shared user-settings query used by profile pages and pages that need display
 * preferences such as currency selection.
 */
export function useUserSettingsQuery() {
  return useQuery({
    queryKey: ['settings', 'user'],
    queryFn: fetchUserSettings,
    staleTime: 60_000
  })
}

/**
 * Shared company-settings query used across settings, print layouts, and pages
 * that need company-level accounting defaults.
 */
export function useCompanySettingsQuery() {
  return useQuery({
    queryKey: ['settings', 'company'],
    queryFn: fetchCompanySettings,
    staleTime: 60_000
  })
}

/**
 * Saves the personal settings form and refreshes both the settings cache and
 * the current `/api/me` snapshot.
 */
export function useUpdateUserSettingsMutation() {
  const queryClient = useQueryClient()

  return useMutation<UserSettingsRecord, Error, UserSettingsFormValues>({
    mutationFn: updateUserSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
    }
  })
}

/**
 * Uploads a user avatar and refreshes the profile views that commonly render
 * it in the header or user-management tables.
 */
export function useUploadUserAvatarMutation() {
  const queryClient = useQueryClient()

  return useMutation<UserSettingsRecord, Error, File>({
    mutationFn: uploadUserAvatar,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
    }
  })
}

/**
 * Removes the current user avatar and keeps profile and header caches aligned.
 */
export function useDeleteUserAvatarMutation() {
  const queryClient = useQueryClient()

  return useMutation<UserSettingsRecord, Error, void>({
    mutationFn: deleteUserAvatar,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'user'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
    }
  })
}

/**
 * Saves company-level settings and refreshes the current session profile so
 * header/company labels stay in sync.
 */
export function useUpdateCompanySettingsMutation() {
  const queryClient = useQueryClient()

  return useMutation<CompanySettingsRecord, Error, CompanySettingsFormValues>({
    mutationFn: updateCompanySettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'company'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
    }
  })
}

/**
 * Uploads the company logo used by settings previews and print layouts.
 */
export function useUploadCompanyLogoMutation() {
  const queryClient = useQueryClient()

  return useMutation<CompanySettingsRecord, Error, File>({
    mutationFn: uploadCompanyLogo,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'company'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
    }
  })
}

/**
 * Deletes the company logo and refreshes settings-driven document previews.
 */
export function useDeleteCompanyLogoMutation() {
  const queryClient = useQueryClient()

  return useMutation<CompanySettingsRecord, Error, void>({
    mutationFn: deleteCompanyLogo,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings', 'company'] })
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
    }
  })
}
