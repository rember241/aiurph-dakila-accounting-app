export const PERMISSION_KEYS = [
  'select',
  'read',
  'write',
  'create',
  'delete',
  'print',
  'email',
  'report',
  'export',
  'import',
  'share',
  'mask'
] as const

export type PermissionKey = (typeof PERMISSION_KEYS)[number]

export const PERMISSION_LABELS: Record<PermissionKey, string> = {
  select: 'Select',
  read: 'View',
  write: 'Edit',
  create: 'Add',
  delete: 'Delete',
  print: 'Print',
  email: 'Send Email',
  report: 'View Reports',
  export: 'Export',
  import: 'Import',
  share: 'Share',
  mask: 'Hide Sensitive Info'
}

export const DEFAULT_READ_PERMISSIONS: PermissionKey[] = ['select', 'read', 'report']
export const DEFAULT_WRITE_PERMISSIONS: PermissionKey[] = ['select', 'read', 'write', 'create', 'delete', 'report']

export function getDefaultPermissionsByAccessLevel(accessLevel: 'read' | 'write'): PermissionKey[] {
  return accessLevel === 'write' ? [...DEFAULT_WRITE_PERMISSIONS] : [...DEFAULT_READ_PERMISSIONS]
}
