import axios, { type AxiosError, type InternalAxiosRequestConfig, isAxiosError, isCancel } from 'axios'

import { parseSessionWindow, reportSessionWindow, reportSessionStatus } from '@/features/auth/model/session-status'

// Send cookies through the page's own origin. Only the Next.js server needs
// the backend address; browser privacy settings and LAN host changes must not
// split the CSRF bootstrap and login across different origins.
const baseURL = '/api/laravel'

export const laravelBrowserClient = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN'
})

function readBrowserCookie(name: string): string | null {
  if (typeof document === 'undefined') return null

  const match = document.cookie
    .split(';')
    .map(v => v.trim())
    .find(v => v.startsWith(`${name}=`))

  if (!match) return null

  try {
    return decodeURIComponent(match.slice(name.length + 1))
  } catch {
    return null
  }
}

type RecoverableRequest = InternalAxiosRequestConfig & { csrfRetried?: boolean }
let csrfRequest: Promise<void> | null = null

laravelBrowserClient.interceptors.request.use(config => {
  // Belt-and-braces CSRF: axios's built-in withXSRFToken handling can misbehave
  // on cross-origin requests in some browsers (different port counts as cross-
  // origin even when host matches). Read the XSRF-TOKEN cookie ourselves and
  // attach the decoded value to X-XSRF-TOKEN so Laravel's VerifyCsrfToken can
  // decrypt it. Safe to set on every request — Laravel only validates for
  // state-changing methods.
  const xsrfToken = readBrowserCookie('XSRF-TOKEN')

  config.headers.set('X-XSRF-TOKEN', xsrfToken)

  return config
})

laravelBrowserClient.interceptors.response.use(
  response => {
    const sessionWindow = parseSessionWindow(
      response.headers['x-session-expires-at'],
      response.headers['x-session-warning-seconds']
    )

    if (sessionWindow) reportSessionWindow(sessionWindow)

    return response
  },
  async (error: unknown) => {
    if (!isAxiosError(error) || isCancel(error)) return Promise.reject(error)

    const status = error.response?.status
    const config = error.config as RecoverableRequest | undefined
    const url = config?.url ?? ''
    const isAuthRequest = /\/api\/(login|logout)(?:[/?]|$)/.test(url)
    const isCsrfRequest = url.includes('/sanctum/csrf-cookie')

    // Laravel rejects a CSRF mismatch before executing the controller. Refresh
    // that token once; never replay mutations after network errors or 5xx.
    if (status === 419 && config && !config.csrfRetried && !isCsrfRequest) {
      config.csrfRetried = true

      try {
        await bootstrapLaravelCsrf()
      } catch (bootstrapError) {
        if (!isAuthRequest && isAxiosError(bootstrapError) &&
            (!bootstrapError.response || bootstrapError.response.status >= 500)) {
          reportSessionStatus('unavailable')
        }

        throw bootstrapError
      }

      return laravelBrowserClient.request(config)
    }

    if (!isAuthRequest && !isCsrfRequest) {
      if (!error.response || (status != null && status >= 500)) {
        reportSessionStatus('unavailable')
      } else if (status === 401) {
        reportSessionStatus('expired')
      }
    }

    // Authentication failures must never invoke logout or delete cookies.
    // Keep the mounted screen (and unsaved forms) intact for recovery.
    return Promise.reject(error)
  }
)

/** Share CSRF bootstrap across simultaneous requests and recover after failure. */
export function bootstrapLaravelCsrf(): Promise<void> {
  csrfRequest ??= laravelBrowserClient.get('/sanctum/csrf-cookie')
    .then(() => {
      if (typeof document !== 'undefined' && !readBrowserCookie('XSRF-TOKEN')) {
        throw new Error('Your browser did not store the sign-in cookie. Allow cookies for this site, then try again.')
      }
    })
    .finally(() => { csrfRequest = null })

  return csrfRequest
}

/**
 * Convenience guard for auth flows that need to react to expired browser
 * sessions.
 */
export function isLaravelUnauthorized(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 401
}

/**
 * Pulls the most useful backend message out of a failed axios response.
 */
export function extractLaravelErrorMessage(error: unknown, fallback: string): string {
  if (!isAxiosError(error)) {
    return error instanceof Error ? error.message : fallback
  }

  const data = error.response?.data as
    | {
        message?: string
        errors?: Record<string, string[]>
      }
    | undefined

  const firstValidationMessage = data?.errors
    ? Object.values(data.errors).flat().find(message => typeof message === 'string' && message.trim() !== '')
    : undefined

  return data?.message ?? firstValidationMessage ?? fallback
}

export type LaravelBrowserAxiosError = AxiosError<{
  message?: string
  errors?: Record<string, string[]>
}>
