// src/features/auth/models/auth.schema.ts
import { z } from 'zod'

import { DEFAULT_PROTECTED_REDIRECT, normalizeProtectedRedirectTarget } from '@/features/auth/model/auth-routes'

export { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'

/**
 * Shared frontend contract for auth and onboarding responses.
 *
 * Most auth consumers only care about `{ user }`, but the extra company and
 * onboarding fields are what drive redirect decisions in the protected route
 * tree.
 */
export const loginInputSchema = z.object({
  email: z.email('Invalid email'),
  password: z.string().min(8, 'Password must be at least 8 characters')
})

const authUserBaseSchema = z.object({
  id: z
    .union([z.number().int().positive(), z.string().regex(/^\d+$/)])
    .transform(value => Number(value)),
  name: z.string(),
  email: z.email(),
  avatar_url: z.string().nullable().optional()
})

const UUID_ANY_VERSION = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

const authCompanySchema = z.object({
  id: z.string().regex(UUID_ANY_VERSION, 'Invalid UUID'),
  name: z.string(),
  slug: z.string(),
  timezone: z.string(),
  logo_url: z.string().nullable().optional()
})

export const onboardingStatusSchema = z.object({
  required: z.boolean(),
  has_company: z.boolean(),
  can_create_company: z.boolean(),
  redirect_to: z.string().min(1)
})

export const authUserSchema = authUserBaseSchema

export const authMeUserSchema = authUserBaseSchema.extend({
  company: authCompanySchema.nullable().optional(),
  onboarding: onboardingStatusSchema
})

export const loginResponseSchema = z.object({
  user: authUserSchema.nullable()
})

export const meResponseSchema = z.object({
  user: authMeUserSchema.nullable()
})

export const logoutResponseSchema = z.object({
  message: z.string()
})

export const apiErrorSchema = z.object({
  message: z.string().optional()
})

export type AuthUser = z.output<typeof authUserSchema>
export type AuthMeUser = z.output<typeof authMeUserSchema>
export type AuthCompany = z.output<typeof authCompanySchema>
export type LoginResponse = z.output<typeof loginResponseSchema>
export type MeResponse = z.output<typeof meResponseSchema>
export type LogoutResponse = z.output<typeof logoutResponseSchema>
export type OnboardingStatus = z.output<typeof onboardingStatusSchema>

/**
 * Protected routes use this helper so onboarding checks stay consistent across
 * SSR layouts and client redirects.
 */
export function requiresCompanyOnboarding(user: Pick<AuthMeUser, 'onboarding'>) {
  return user.onboarding.required
}

/**
 * Normalizes older backend redirect targets into the current protected routing
 * model before the UI consumes them.
 */
export function getPostOnboardingRedirect(user: Pick<AuthMeUser, 'onboarding'>) {
  return normalizeProtectedRedirectTarget(user.onboarding.redirect_to)
}

/**
 * Small utility used when the frontend needs to optimistically mark onboarding
 * as complete after company setup succeeds.
 */
export function createCompletedOnboardingStatus(): OnboardingStatus {
  return {
    required: false,
    has_company: true,
    can_create_company: false,
    redirect_to: DEFAULT_PROTECTED_REDIRECT
  }
}
