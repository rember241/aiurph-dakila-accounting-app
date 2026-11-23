import type { RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'
import { getRoleAccessPreset } from '@/features/(protected)/users/roles/model/role-access-control'

const now = Date.now()

const daysAgo = (days: number) => new Date(now - days * 24 * 60 * 60 * 1000).toISOString()

export const ROLE_DEPARTMENT_OPTIONS = [
  'Finance',
  'Accounting',
  'Treasury',
  'Operations',
  'Internal Audit',
  'Shared Services'
] as const

const ROLES_BASE: Omit<
  RoleRecord,
  | 'isAdminRole'
  | 'canEdit'
  | 'canDelete'
  | 'canDeactivate'
  | 'orgUnit'
  | 'permissions'
  | 'permissionScope'
  | 'rawPermissionScope'
  | 'modulePermissions'
>[] = [
  {
    id: 'rol-001',
    tenantId: null,
    systemGenerated: true,
    roleType: 'system',
    name: 'System Administrator',
    key: 'SYSTEM_ADMIN',
    department: 'Finance',
    status: 'enabled',
    accessLevel: 'write',
    description: 'Full control over user setup and accounting system configuration.',
    members: [
      { id: 'usr-001', fullName: 'Arielle Mendoza', email: 'arielle.mendoza@aiur-demo.com' },
      { id: 'usr-002', fullName: 'Gabriel Santos', email: 'gabriel.santos@aiur-demo.com' }
    ],
    rawModulePermissions: {},
    membersCount: 2,
    createdAt: daysAgo(30),
    lastUpdated: daysAgo(1)
  },
  {
    id: 'rol-002',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'Finance Manager',
    key: 'FINANCE_MANAGER',
    department: 'Finance',
    status: 'enabled',
    accessLevel: 'write',
    description: 'Approves and oversees high-impact accounting operations.',
    members: [
      { id: 'usr-003', fullName: 'Lea Villanueva', email: 'lea.villanueva@aiur-demo.com' },
      { id: 'usr-004', fullName: 'Noel Ortega', email: 'noel.ortega@aiur-demo.com' }
    ],
    rawModulePermissions: {},
    membersCount: 2,
    createdAt: daysAgo(28),
    lastUpdated: daysAgo(2)
  },
  {
    id: 'rol-003',
    tenantId: null,
    systemGenerated: true,
    roleType: 'system',
    name: 'Accountant',
    key: 'ACCOUNTANT',
    department: 'Accounting',
    status: 'enabled',
    accessLevel: 'write',
    description: 'Posts journal entries and manages chart of accounts operations.',
    members: [
      { id: 'usr-005', fullName: 'Ella Bautista', email: 'ella.bautista@aiur-demo.com' },
      { id: 'usr-006', fullName: 'Marc Co', email: 'marc.co@aiur-demo.com' },
      { id: 'usr-007', fullName: 'Ivy Navarro', email: 'ivy.navarro@aiur-demo.com' }
    ],
    rawModulePermissions: {},
    membersCount: 3,
    createdAt: daysAgo(26),
    lastUpdated: daysAgo(4)
  },
  {
    id: 'rol-004',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'Treasury Analyst',
    key: 'TREASURY_ANALYST',
    department: 'Treasury',
    status: 'enabled',
    accessLevel: 'read',
    description: 'Monitors cash and treasury balances with read-focused access.',
    members: [{ id: 'usr-008', fullName: 'Paolo Reyes', email: 'paolo.reyes@aiur-demo.com' }],
    rawModulePermissions: {},
    membersCount: 1,
    createdAt: daysAgo(22),
    lastUpdated: daysAgo(7)
  },
  {
    id: 'rol-005',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'Internal Auditor',
    key: 'INTERNAL_AUDITOR',
    department: 'Internal Audit',
    status: 'disabled',
    accessLevel: 'read',
    description: 'Reviews audit logs and validates controls with read-only access.',
    members: [{ id: 'usr-009', fullName: 'Janelle Lim', email: 'janelle.lim@aiur-demo.com' }],
    rawModulePermissions: {},
    membersCount: 1,
    createdAt: daysAgo(21),
    lastUpdated: daysAgo(13)
  },
  {
    id: 'rol-006',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'AP Specialist',
    key: 'AP_SPECIALIST',
    department: 'Shared Services',
    status: 'enabled',
    accessLevel: 'write',
    description: 'Handles accounts payable transactions and approvals in assigned scope.',
    members: [{ id: 'usr-010', fullName: 'Rhea Abad', email: 'rhea.abad@aiur-demo.com' }],
    rawModulePermissions: {},
    membersCount: 1,
    createdAt: daysAgo(24),
    lastUpdated: daysAgo(5)
  },
  {
    id: 'rol-007',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'AR Specialist',
    key: 'AR_SPECIALIST',
    department: 'Shared Services',
    status: 'enabled',
    accessLevel: 'write',
    description: 'Handles receivables workflow and customer-facing ledger updates.',
    members: [{ id: 'usr-011', fullName: 'Carlo Estrella', email: 'carlo.estrella@aiur-demo.com' }],
    rawModulePermissions: {},
    membersCount: 1,
    createdAt: daysAgo(23),
    lastUpdated: daysAgo(6)
  },
  {
    id: 'rol-008',
    tenantId: null,
    systemGenerated: false,
    roleType: 'custom',
    name: 'Operations Viewer',
    key: 'OPS_VIEWER',
    department: 'Operations',
    status: 'disabled',
    accessLevel: 'read',
    description: 'Cross-functional read-only access for operational monitoring.',
    members: [{ id: 'usr-012', fullName: 'Nina Cruz', email: 'nina.cruz@aiur-demo.com' }],
    rawModulePermissions: {},
    membersCount: 1,
    createdAt: daysAgo(20),
    lastUpdated: daysAgo(18)
  }
]

export const MOCK_ROLE_RECORDS: RoleRecord[] = ROLES_BASE.map(role => {
  const preset = getRoleAccessPreset(role.key, role.accessLevel)
  const normalizedKey = role.key.trim().toUpperCase()
  const isAdminRole = normalizedKey === 'ADMIN' || normalizedKey === 'SYSTEM_ADMIN'

  return {
    ...role,
    isAdminRole,
    canEdit: !isAdminRole,
    canDelete: !isAdminRole,
    canDeactivate: !isAdminRole,
    orgUnit: null,
    permissionScope: preset.permissionScope,
    rawPermissionScope: preset.permissionScope === 'all' ? 'global' : 'department',
    modulePermissions: preset.modulePermissions,
    permissions: preset.permissions
  }
})
