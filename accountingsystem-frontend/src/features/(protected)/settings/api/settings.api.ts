import { bootstrapLaravelCsrf, laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import {
  companySettingsInputSchema,
  companySettingsResponseSchema,
  settingsApiErrorSchema,
  userSettingsInputSchema,
  userSettingsResponseSchema
} from '@/features/(protected)/settings/model/settings.schema'

import type {
  CompanySettingsFormValues,
  CompanySettingsRecord,
  SettingsApiFieldErrors,
  UserSettingsFormValues,
  UserSettingsRecord
} from '@/features/(protected)/settings/model/settings.schema'

export class SettingsApiError extends Error {
  readonly status: number
  readonly fieldErrors: SettingsApiFieldErrors

  constructor(message: string, status: number, fieldErrors: SettingsApiFieldErrors = null) {
    super(message)
    this.name = 'SettingsApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

type SettingsErrorAction = 'load' | 'save'
type SettingsErrorScope = 'user' | 'company'

const SETTINGS_ERROR_COPY = {
  user: {
    load: "We couldn't open your profile settings right now. Refresh the page or try again in a moment.",
    save: "We couldn't save your profile changes right now. Please try again in a moment.",
    validation: 'Some profile details need your attention. Check the highlighted fields and try again.',
    forbiddenView: "You don't have access to profile settings right now.",
    forbiddenSave: "You don't have permission to save profile changes right now.",
    missing: 'Your profile settings are not available right now. Refresh the page and try again.'
  },
  company: {
    load: "We couldn't open company settings right now. Refresh the page or try again in a moment.",
    save: "We couldn't save your company changes right now. Please try again in a moment.",
    validation: 'Some company details need your attention. Check the highlighted fields and try again.',
    forbiddenView: "You don't have access to company settings right now.",
    forbiddenSave: 'You can review company details here, but only company owners and admins can save changes.',
    missing: 'Company settings are not available right now. Refresh the page and try again.'
  }
} as const

/**
 * Maps low-level API failures to the copy used by settings pages.
 *
 * The pages still surface field-level errors inline, so this helper focuses on
 * the top-level toast or banner message only.
 */
export function getFriendlySettingsErrorMessage(
  error: unknown,
  options: {
    action: SettingsErrorAction
    scope: SettingsErrorScope
  }
) {
  const copy = SETTINGS_ERROR_COPY[options.scope]

  if (error instanceof SettingsApiError) {
    if (error.status === 401) {
      return 'Your session has ended. Sign in again to continue.'
    }

    if (error.status === 403) {
      return options.action === 'save' ? copy.forbiddenSave : copy.forbiddenView
    }

    if (error.status === 404) {
      return copy.missing
    }

    if (error.status === 422) {
      return copy.validation
    }
  }

  return options.action === 'save' ? copy.save : copy.load
}

/**
 * Loads the authenticated user's personal settings from Laravel.
 */
export async function fetchUserSettings(): Promise<UserSettingsRecord> {
  const response = await laravelBrowserClient.get('/api/v1/settings/user')

  const parsed = userSettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Persists personal profile preferences for the authenticated user.
 */
export async function updateUserSettings(values: UserSettingsFormValues): Promise<UserSettingsRecord> {
  const payload = userSettingsInputSchema.parse(values)

  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.put('/api/v1/settings/user', payload)

  const parsed = userSettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Uploads a replacement profile image through the settings endpoint.
 */
export async function uploadUserAvatar(file: File): Promise<UserSettingsRecord> {
  const formData = new FormData()

  formData.append('avatar', file)

  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.post('/api/v1/settings/user/avatar', formData)

  const parsed = userSettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Removes the current profile image without changing the rest of the form.
 */
export async function deleteUserAvatar(): Promise<UserSettingsRecord> {
  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.delete('/api/v1/settings/user/avatar')

  const parsed = userSettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Loads company-level settings for the signed-in user's current company.
 */
export async function fetchCompanySettings(): Promise<CompanySettingsRecord> {
  const response = await laravelBrowserClient.get('/api/v1/settings/company')

  const parsed = companySettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Saves the editable company profile, printing, and default-account settings.
 */
export async function updateCompanySettings(values: CompanySettingsFormValues): Promise<CompanySettingsRecord> {
  const payload = companySettingsInputSchema.parse(values)

  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.put('/api/v1/settings/company', payload)

  const parsed = companySettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Uploads the company logo used by settings previews and printable documents.
 */
export async function uploadCompanyLogo(file: File): Promise<CompanySettingsRecord> {
  const formData = new FormData()

  formData.append('logo', file)

  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.post('/api/v1/settings/company/logo', formData)

  const parsed = companySettingsResponseSchema.parse(response.data)

  return parsed.data
}

/**
 * Deletes the current company logo while leaving the rest of the company
 * settings untouched.
 */
export async function deleteCompanyLogo(): Promise<CompanySettingsRecord> {
  await bootstrapLaravelCsrf()

  const response = await laravelBrowserClient.delete('/api/v1/settings/company/logo')

  const parsed = companySettingsResponseSchema.parse(response.data)

  return parsed.data
}
