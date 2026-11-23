import type { PermissionKey } from '@/features/(protected)/users/permissions'
import { PERMISSION_KEYS } from '@/features/(protected)/users/permissions'
import type { RoleAccessLevel } from '@/features/(protected)/users/roles/model/roles.types'

export const ROLE_PERMISSION_SCOPES = ['all', 'page'] as const
export type RolePermissionScope = (typeof ROLE_PERMISSION_SCOPES)[number]

export type RolePermissionAction = {
  key: PermissionKey
  label: string
  description: string
}

export type RolePermissionModule = {
  key: string
  label: string
  description: string
  actions: RolePermissionAction[]
}

export const ROLE_PERMISSION_MODULES: RolePermissionModule[] = [
  {
    key: 'dashboard_reports',
    label: 'Dashboard & Reports',
    description: 'See business performance and reporting pages',
    actions: [{ key: 'read', label: 'View', description: 'Open dashboards, summaries, and report pages' }]
  },
  {
    key: 'accounting',
    label: 'Chart of Accounts',
    description: 'Manage the company account list and setup',
    actions: [
      { key: 'read', label: 'View', description: 'See the account list and structure' },
      { key: 'create', label: 'Add New', description: 'Create new accounts in this area' },
      { key: 'write', label: 'Edit', description: 'Update existing accounts in this area' },
      { key: 'delete', label: 'Delete', description: 'Remove custom accounts when allowed' }
    ]
  },
  {
    key: 'journal',
    label: 'Journal Entries',
    description: 'Work with accounting entries and drafts',
    actions: [
      { key: 'read', label: 'View', description: 'See journal entries and entry details' },
      { key: 'create', label: 'Add New', description: 'Create new journal entries' },
      {
        key: 'write',
        label: 'Edit, Post & Void',
        description: 'Update drafts, post entries, and void entries when allowed'
      }
    ]
  },
  {
    key: 'period',
    label: 'Period Management',
    description: 'Manage fiscal years and period status',
    actions: [
      { key: 'read', label: 'View', description: 'See fiscal years and accounting periods' },
      { key: 'create', label: 'Add Fiscal Year', description: 'Create new fiscal years' },
      {
        key: 'write',
        label: 'Edit & Change Status',
        description: 'Update fiscal years and open, close, or set current periods'
      },
      { key: 'delete', label: 'Delete', description: 'Remove fiscal years when allowed' }
    ]
  },
  {
    key: 'sales',
    label: 'Sales Invoices',
    description: 'Work with draft and posted sales invoices',
    actions: [
      { key: 'read', label: 'View', description: 'See invoices and sales activity' },
      { key: 'create', label: 'Add New', description: 'Create draft sales invoices' },
      { key: 'write', label: 'Edit & Post', description: 'Update draft invoices and post them' },
      { key: 'delete', label: 'Delete', description: 'Delete draft invoices when allowed' }
    ]
  },
  {
    key: 'customers',
    label: 'Customers',
    description: 'Manage customer records and status',
    actions: [
      { key: 'read', label: 'View', description: 'See customer records and details' },
      { key: 'create', label: 'Add New', description: 'Create customer records' },
      {
        key: 'write',
        label: 'Edit & Change Status',
        description: 'Update customer details and activate or deactivate them'
      },
      { key: 'delete', label: 'Delete', description: 'Delete customer records when allowed' }
    ]
  },
  {
    key: 'inventory',
    label: 'Inventory Items',
    description: 'Manage item records and stock status',
    actions: [
      { key: 'read', label: 'View', description: 'See item records and stock views' },
      { key: 'create', label: 'Add New', description: 'Create item records' },
      {
        key: 'write',
        label: 'Edit & Change Status',
        description: 'Update item details and activate or deactivate them'
      },
      { key: 'delete', label: 'Delete', description: 'Delete item records when allowed' }
    ]
  },
  {
    key: 'expenses',
    label: 'Expenses & Bills',
    description: 'Manage bills and expense records',
    actions: [
      { key: 'read', label: 'View', description: 'See expense and bill pages' },
      { key: 'create', label: 'Add New', description: 'Create bills and expense records' },
      { key: 'write', label: 'Edit', description: 'Update bills and expense records' },
      { key: 'delete', label: 'Delete', description: 'Delete bills and expense records when allowed' }
    ]
  },
  {
    key: 'users',
    label: 'Users',
    description: 'Manage user accounts in this company',
    actions: [
      { key: 'read', label: 'View', description: 'See users and account details' },
      { key: 'create', label: 'Add New', description: 'Invite or create users' },
      {
        key: 'write',
        label: 'Edit & Change Status',
        description: 'Update user details and activate or deactivate accounts'
      },
      { key: 'delete', label: 'Delete', description: 'Remove users from the company when allowed' }
    ]
  },
  {
    key: 'roles',
    label: 'Roles',
    description: 'Manage role profiles and access rules',
    actions: [
      { key: 'read', label: 'View', description: 'See role profiles and permission settings' },
      { key: 'create', label: 'Add New', description: 'Create new role profiles' },
      { key: 'write', label: 'Edit & Change Status', description: 'Update role details and enable or disable roles' },
      { key: 'delete', label: 'Delete', description: 'Remove roles when allowed' }
    ]
  },
  {
    key: 'org_units',
    label: 'Company Structure',
    description: 'Manage departments, branches, and reporting structure',
    actions: [
      { key: 'read', label: 'View', description: 'See company structure and org units' },
      { key: 'create', label: 'Add New', description: 'Create new departments, branches, or units' },
      {
        key: 'write',
        label: 'Edit & Change Status',
        description: 'Update structure details and activate or deactivate org units'
      },
      { key: 'delete', label: 'Delete', description: 'Remove org units when allowed' }
    ]
  },
  {
    key: 'settings',
    label: 'Company Settings',
    description: 'Manage company profile, document printing, and accounting defaults',
    actions: [
      { key: 'read', label: 'View', description: 'See company settings and document defaults' },
      { key: 'write', label: 'Edit', description: 'Update company settings, logos, and document defaults' }
    ]
  }
].filter(module =>
  ['accounting', 'journal', 'period', 'roles'].includes(module.key)
) as RolePermissionModule[]

export type RolePermissionModuleKey = (typeof ROLE_PERMISSION_MODULES)[number]['key']
export type RoleModulePermissions = Record<RolePermissionModuleKey, PermissionKey[]>

const READ_ALLOWED_ACTIONS = new Set<PermissionKey>(['select', 'read', 'report'])

const ENFORCED_ROLE_ACTIONS: Record<RolePermissionModuleKey, PermissionKey[]> = {
  dashboard_reports: ['read'],
  accounting: ['read', 'create', 'write', 'delete'],
  journal: ['read', 'create', 'write'],
  period: ['read', 'create', 'write', 'delete'],
  sales: ['read', 'create', 'write', 'delete'],
  customers: ['read', 'create', 'write', 'delete'],
  inventory: ['read', 'create', 'write', 'delete'],
  expenses: ['read', 'create', 'write', 'delete'],
  users: ['read', 'create', 'write', 'delete'],
  roles: ['read', 'create', 'write', 'delete'],
  org_units: ['read', 'create', 'write', 'delete'],
  settings: ['read', 'write']
}

const ROLE_TEMPLATE_LABELS: Record<string, string> = {
  SYSTEM_ADMIN: 'System Admin Template',
  FINANCE_MANAGER: 'Finance Manager Template',
  ACCOUNTANT: 'Accountant Template',
  TREASURY_ANALYST: 'Treasury Analyst Template',
  INTERNAL_AUDITOR: 'Internal Auditor Template',
  AP_SPECIALIST: 'AP Specialist Template',
  AR_SPECIALIST: 'AR Specialist Template',
  OPS_VIEWER: 'Operations Viewer Template'
}

const ROLE_TEMPLATE_CONFIG: Record<string, { scope: RolePermissionScope; moduleKeys: RolePermissionModuleKey[] }> = {
  SYSTEM_ADMIN: { scope: 'all', moduleKeys: ROLE_PERMISSION_MODULES.map(module => module.key) },
  FINANCE_MANAGER: {
    scope: 'page',
    moduleKeys: ['dashboard_reports', 'accounting', 'journal', 'period', 'expenses', 'settings']
  },
  ACCOUNTANT: {
    scope: 'page',
    moduleKeys: ['dashboard_reports', 'accounting', 'journal', 'period']
  },
  TREASURY_ANALYST: { scope: 'page', moduleKeys: ['dashboard_reports', 'period'] },
  INTERNAL_AUDITOR: { scope: 'page', moduleKeys: ['dashboard_reports', 'accounting', 'journal'] },
  AP_SPECIALIST: { scope: 'page', moduleKeys: ['expenses', 'accounting', 'dashboard_reports'] },
  AR_SPECIALIST: { scope: 'page', moduleKeys: ['sales', 'customers', 'accounting', 'dashboard_reports'] },
  OPS_VIEWER: { scope: 'page', moduleKeys: ['dashboard_reports', 'sales', 'customers', 'inventory', 'expenses'] }
}

function toKey(roleKey: string) {
  return roleKey.trim().toUpperCase()
}

export function getModuleActionKeysByAccessLevel(moduleKey: RolePermissionModuleKey, accessLevel: RoleAccessLevel) {
  const moduleDef = ROLE_PERMISSION_MODULES.find(item => item.key === moduleKey)

  if (!moduleDef) return []

  return moduleDef.actions
    .filter(action => (accessLevel === 'write' ? true : READ_ALLOWED_ACTIONS.has(action.key)))
    .map(action => action.key)
}

export function isRolePermissionActionEnforced(moduleKey: RolePermissionModuleKey, actionKey: PermissionKey) {
  return (ENFORCED_ROLE_ACTIONS[moduleKey] ?? []).includes(actionKey)
}

export function getEditableRoleActionKeysByAccessLevel(
  moduleKey: RolePermissionModuleKey,
  accessLevel: RoleAccessLevel
) {
  return getModuleActionKeysByAccessLevel(moduleKey, accessLevel).filter(action =>
    isRolePermissionActionEnforced(moduleKey, action)
  )
}

export function createEmptyRoleModulePermissions(): RoleModulePermissions {
  return ROLE_PERMISSION_MODULES.reduce((acc, module) => {
    acc[module.key] = []

    return acc
  }, {} as RoleModulePermissions)
}

export function createAllPagesModulePermissions(accessLevel: RoleAccessLevel): RoleModulePermissions {
  return ROLE_PERMISSION_MODULES.reduce((acc, module) => {
    acc[module.key] = getEditableRoleActionKeysByAccessLevel(module.key, accessLevel)

    return acc
  }, {} as RoleModulePermissions)
}

export function flattenRolePermissions(modulePermissions: RoleModulePermissions): PermissionKey[] {
  const selected = new Set<PermissionKey>()

  Object.values(modulePermissions).forEach(actions => {
    actions.forEach(action => {
      selected.add(action)
    })
  })

  return PERMISSION_KEYS.filter(key => selected.has(key))
}

export function getRoleAccessPreset(roleKey: string, accessLevel: RoleAccessLevel) {
  const normalizedKey = toKey(roleKey)
  const template = ROLE_TEMPLATE_CONFIG[normalizedKey]
  const isKnownTemplate = Boolean(template)

  const selectedTemplate = template ?? {
    scope: accessLevel === 'write' ? 'all' : 'page',
    moduleKeys: accessLevel === 'write' ? ROLE_PERMISSION_MODULES.map(module => module.key) : ['accounting']
  }

  const modulePermissions = createEmptyRoleModulePermissions()

  selectedTemplate.moduleKeys
    .filter(key => ROLE_PERMISSION_MODULES.some(module => module.key === key))
    .forEach(moduleKey => {
      modulePermissions[moduleKey] = getEditableRoleActionKeysByAccessLevel(moduleKey, accessLevel)
    })

  const permissionScope = selectedTemplate.scope

  return {
    permissionScope,
    modulePermissions,
    permissions: flattenRolePermissions(modulePermissions),
    templateLabel: ROLE_TEMPLATE_LABELS[normalizedKey] ?? 'Custom Template',
    isKnownTemplate
  }
}

export function enforceScopePermissions(
  scope: RolePermissionScope,
  modulePermissions: RoleModulePermissions,
  accessLevel: RoleAccessLevel
) {
  if (scope === 'all') {
    const allModules = createAllPagesModulePermissions(accessLevel)

    return {
      modulePermissions: allModules,
      permissions: flattenRolePermissions(allModules)
    }
  }

  const normalized = createEmptyRoleModulePermissions()

  ROLE_PERMISSION_MODULES.forEach(module => {
    const availableActionKeys = new Set(getEditableRoleActionKeysByAccessLevel(module.key, accessLevel))

    normalized[module.key] = (modulePermissions[module.key] ?? []).filter(action => availableActionKeys.has(action))
  })

  return {
    modulePermissions: normalized,
    permissions: flattenRolePermissions(normalized)
  }
}
