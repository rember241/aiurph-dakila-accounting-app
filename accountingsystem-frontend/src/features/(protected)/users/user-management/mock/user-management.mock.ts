import type { UserRecord } from '@/features/(protected)/users/user-management/model/user-management.types'
import { getDefaultPermissionsByAccessLevel } from '@/features/(protected)/users/permissions'

const now = Date.now()

const hoursAgo = (hours: number) => new Date(now - hours * 60 * 60 * 1000).toISOString()
const daysAgo = (days: number) => new Date(now - days * 24 * 60 * 60 * 1000).toISOString()

export const USER_ROLE_OPTIONS = [
  'System Administrator',
  'Finance Manager',
  'Accountant',
  'AP Specialist',
  'AR Specialist',
  'Treasury Analyst',
  'Controller',
  'Internal Auditor'
] as const

export const USER_ORG_UNIT_OPTIONS = [
  'Finance Department',
  'Accounting Department',
  'Treasury Department',
  'Operations Department',
  'Internal Audit Team',
  'Shared Services Team'
] as const

type MockUserSeed = Pick<
  UserRecord,
  'id' | 'avatarUrl' | 'fullName' | 'email' | 'role' | 'status' | 'accessLevel' | 'lastActive'
> & {
  primaryOrgUnitLabel: string
}

const USERS_BASE: MockUserSeed[] = [
  {
    id: 'usr-0001',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Arielle Mendoza',
    email: 'arielle.mendoza@aiur-demo.com',
    role: 'System Administrator',
    primaryOrgUnitLabel: 'Finance Department',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: hoursAgo(1)
  },
  {
    id: 'usr-0002',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Gabriel Santos',
    email: 'gabriel.santos@aiur-demo.com',
    role: 'Finance Manager',
    primaryOrgUnitLabel: 'Finance Department',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: hoursAgo(2)
  },
  {
    id: 'usr-0003',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Lea Villanueva',
    email: 'lea.villanueva@aiur-demo.com',
    role: 'Controller',
    primaryOrgUnitLabel: 'Accounting Department',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: hoursAgo(5)
  },
  {
    id: 'usr-0004',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Noel Ortega',
    email: 'noel.ortega@aiur-demo.com',
    role: 'Accountant',
    primaryOrgUnitLabel: 'Accounting Department',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: hoursAgo(8)
  },
  {
    id: 'usr-0005',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Ella Bautista',
    email: 'ella.bautista@aiur-demo.com',
    role: 'Accountant',
    primaryOrgUnitLabel: 'Accounting Department',
    status: 'enabled',
    accessLevel: 'read',
    lastActive: hoursAgo(11)
  },
  {
    id: 'usr-0006',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Marc Co',
    email: 'marc.co@aiur-demo.com',
    role: 'AP Specialist',
    primaryOrgUnitLabel: 'Shared Services Team',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: daysAgo(1)
  },
  {
    id: 'usr-0007',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Ivy Navarro',
    email: 'ivy.navarro@aiur-demo.com',
    role: 'AR Specialist',
    primaryOrgUnitLabel: 'Shared Services Team',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: daysAgo(1)
  },
  {
    id: 'usr-0008',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Paolo Reyes',
    email: 'paolo.reyes@aiur-demo.com',
    role: 'Treasury Analyst',
    primaryOrgUnitLabel: 'Treasury Department',
    status: 'enabled',
    accessLevel: 'read',
    lastActive: daysAgo(2)
  },
  {
    id: 'usr-0009',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Janelle Lim',
    email: 'janelle.lim@aiur-demo.com',
    role: 'Internal Auditor',
    primaryOrgUnitLabel: 'Internal Audit Team',
    status: 'enabled',
    accessLevel: 'read',
    lastActive: daysAgo(2)
  },
  {
    id: 'usr-0010',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Rhea Abad',
    email: 'rhea.abad@aiur-demo.com',
    role: 'Accountant',
    primaryOrgUnitLabel: 'Operations Department',
    status: 'disabled',
    accessLevel: 'read',
    lastActive: daysAgo(6)
  },
  {
    id: 'usr-0011',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Carlo Estrella',
    email: 'carlo.estrella@aiur-demo.com',
    role: 'AP Specialist',
    primaryOrgUnitLabel: 'Operations Department',
    status: 'disabled',
    accessLevel: 'read',
    lastActive: daysAgo(10)
  },
  {
    id: 'usr-0012',
    avatarUrl: '/images/avatars/1.png',
    fullName: 'Nina Cruz',
    email: 'nina.cruz@aiur-demo.com',
    role: 'AR Specialist',
    primaryOrgUnitLabel: 'Operations Department',
    status: 'enabled',
    accessLevel: 'write',
    lastActive: daysAgo(3)
  }
]

function createMockOrgUnitSummary(user: MockUserSeed): NonNullable<UserRecord['primaryOrgUnit']> {
  return {
    id: `${user.id}-org-unit`,
    name: user.primaryOrgUnitLabel,
    code: user.primaryOrgUnitLabel
      .toUpperCase()
      .replace(/[^A-Z0-9]+/g, '-')
      .replace(/^-+|-+$/g, ''),
    type: user.primaryOrgUnitLabel.toLowerCase().includes('team') ? 'team' : 'department',
    status: 'active' as const
  }
}

export const MOCK_USER_MANAGEMENT_USERS: UserRecord[] = USERS_BASE.map(user => ({
  ...user,
  isOwner: false,
  isCurrentUser: false,
  canEdit: true,
  canDelete: true,
  membership: null,
  roleIds: [],
  roleNames: user.role ? [user.role] : [],
  roles: [],
  primaryOrgUnit: createMockOrgUnitSummary(user),
  additionalOrgUnits: [],
  roleAssignmentScope: 'primary_org_unit',
  scopeOrgUnits: [createMockOrgUnitSummary(user)],
  primaryOrgUnitLabel: user.primaryOrgUnitLabel,
  operatingScopeLabel: `Primary org unit: ${user.primaryOrgUnitLabel}`,
  legacyDepartment: user.primaryOrgUnitLabel,
  permissions: getDefaultPermissionsByAccessLevel(user.accessLevel),
  createdAt: user.lastActive,
  updatedAt: user.lastActive
}))
