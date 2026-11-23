export const UNASSIGNED_DEPARTMENT_LABEL = 'Unassigned'
export const UNASSIGNED_DEPARTMENT_FILTER_VALUE = '__unassigned__'

export const DEFAULT_DEPARTMENT_OPTIONS = [
  'Accounting',
  'Administration',
  'Finance',
  'Internal Audit',
  'Operations',
  'Shared Services',
  'Treasury'
] as const

const UNASSIGNED_DEPARTMENT_ALIASES = new Set(['', 'unassigned', 'no department'])

export function normalizeDepartmentValue(value: string | null | undefined): string {
  const normalized = typeof value === 'string' ? value.trim() : ''

  if (UNASSIGNED_DEPARTMENT_ALIASES.has(normalized.toLowerCase())) {
    return ''
  }

  return normalized
}

export function isUnassignedDepartment(value: string | null | undefined): boolean {
  return normalizeDepartmentValue(value) === ''
}

export function getDepartmentDisplayLabel(value: string | null | undefined): string {
  return normalizeDepartmentValue(value) || UNASSIGNED_DEPARTMENT_LABEL
}

export function toDepartmentApiValue(value: string | null | undefined): string | null {
  const normalized = normalizeDepartmentValue(value)

  return normalized === '' ? null : normalized
}

export function buildDepartmentOptions(values: Array<string | null | undefined>): string[] {
  return Array.from(new Set([...DEFAULT_DEPARTMENT_OPTIONS, ...values.map(normalizeDepartmentValue).filter(Boolean)])).sort(
    (a, b) => a.localeCompare(b)
  )
}

export function matchesDepartmentFilter(
  value: string | null | undefined,
  filterValue: string | null | undefined
): boolean {
  const normalizedFilter = normalizeDepartmentValue(filterValue)

  if (!filterValue || normalizedFilter === '') {
    return filterValue === UNASSIGNED_DEPARTMENT_FILTER_VALUE ? isUnassignedDepartment(value) : true
  }

  if (filterValue === UNASSIGNED_DEPARTMENT_FILTER_VALUE) {
    return isUnassignedDepartment(value)
  }

  return normalizeDepartmentValue(value) === normalizedFilter
}
