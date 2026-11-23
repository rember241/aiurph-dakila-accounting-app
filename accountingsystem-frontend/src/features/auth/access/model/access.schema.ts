import { z } from 'zod'

import { currentTenantIdSchema } from '@/features/(protected)/users/tenants/model/current-tenant.schema'

/**
 * Central list of frontend ability keys.
 *
 * These mirror the backend permission strings so layouts, route handlers, and
 * action buttons can all speak the same language.
 */
export const APP_ABILITIES = {
  dashboardReportsRead: 'dashboard_reports.read',
  accountingRead: 'accounting.read',
  accountingCreate: 'accounting.create',
  accountingWrite: 'accounting.write',
  accountingDelete: 'accounting.delete',
  journalRead: 'journal.read',
  journalCreate: 'journal.create',
  journalWrite: 'journal.write',
  periodRead: 'period.read',
  periodCreate: 'period.create',
  periodWrite: 'period.write',
  periodDelete: 'period.delete',
  orgUnitsRead: 'org_units.read',
  orgUnitsCreate: 'org_units.create',
  orgUnitsWrite: 'org_units.write',
  orgUnitsDelete: 'org_units.delete',
  usersRead: 'users.read',
  usersCreate: 'users.create',
  usersWrite: 'users.write',
  usersDelete: 'users.delete',
  rolesRead: 'roles.read',
  rolesCreate: 'roles.create',
  rolesWrite: 'roles.write',
  rolesDelete: 'roles.delete',
  settingsRead: 'settings.read',
  settingsWrite: 'settings.write',
  salesRead: 'sales.read',
  salesCreate: 'sales.create',
  salesWrite: 'sales.write',
  salesDelete: 'sales.delete',
  inventoryRead: 'inventory.read',
  inventoryCreate: 'inventory.create',
  inventoryWrite: 'inventory.write',
  inventoryDelete: 'inventory.delete',
  customersRead: 'customers.read',
  customersCreate: 'customers.create',
  customersWrite: 'customers.write',
  customersDelete: 'customers.delete',
  expensesRead: 'expenses.read',
  expensesCreate: 'expenses.create',
  expensesWrite: 'expenses.write',
  expensesDelete: 'expenses.delete'
} as const

export const appAbilitySchema = z.string().regex(/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/)

const currentTenantAccessShapeSchema = z.object({
  tenantId: currentTenantIdSchema.nullable(),
  fullAccess: z.boolean(),
  abilities: z.array(appAbilitySchema).default([]),
  disabledModules: z.array(z.string()).default([])
})

const currentTenantAccessApiSchema = z.union([
  currentTenantAccessShapeSchema,
  z
    .object({
      tenant_id: currentTenantIdSchema.nullable(),
      full_access: z.boolean(),
      abilities: z.array(appAbilitySchema).default([]),
      disabled_modules: z.array(z.string()).default([])
    })
    .transform(data => ({
      tenantId: data.tenant_id,
      fullAccess: data.full_access,
        abilities: data.abilities,
        disabledModules: data.disabled_modules
    }))
])

export const currentTenantAccessSchema = currentTenantAccessApiSchema

export const currentTenantAccessStateSchema = z.object({
  data: currentTenantAccessApiSchema
})

export const currentTenantAccessApiErrorSchema = z.object({
  message: z.string().optional()
})

export type AppAbility = (typeof APP_ABILITIES)[keyof typeof APP_ABILITIES]
export type CurrentTenantAccessState = z.output<typeof currentTenantAccessStateSchema>

type CurrentTenantAccessLike = Pick<CurrentTenantAccessState['data'], 'fullAccess' | 'abilities' | 'disabledModules'>

/**
 * Safe empty-state used before the company access snapshot is available.
 */
export function createEmptyCurrentTenantAccessState(tenantId: string | null = null): CurrentTenantAccessState {
  return currentTenantAccessStateSchema.parse({
    data: {
      tenantId,
      fullAccess: false,
      abilities: [],
      disabledModules: []
    }
  })
}

function normalizeAbilityList(abilities: readonly string[]) {
  return new Set(abilities)
}

/**
 * Single-ability check used by most UI gates.
 */
export function hasCurrentTenantAbility(access: CurrentTenantAccessLike, ability: string) {
  const module = ability.trim().toLowerCase().split('.')[0]

  if (access.disabledModules.includes(module)) {
    return false
  }

  if (access.fullAccess) {
    return true
  }

  return normalizeAbilityList(access.abilities).has(ability)
}

/**
 * Used by parent menus and composite pages that can open when any one of
 * several capabilities is present.
 */
export function hasAnyCurrentTenantAbility(access: CurrentTenantAccessLike, abilities: readonly string[]) {
  if (abilities.length === 0) {
    return true
  }

  return abilities.some(ability => hasCurrentTenantAbility(access, ability))
}

/**
 * Used sparingly for actions that require the full permission set, not just one
 * matching ability.
 */
export function hasAllCurrentTenantAbilities(access: CurrentTenantAccessLike, abilities: readonly string[]) {
  if (abilities.length === 0) {
    return true
  }

  return abilities.every(ability => hasCurrentTenantAbility(access, ability))
}
