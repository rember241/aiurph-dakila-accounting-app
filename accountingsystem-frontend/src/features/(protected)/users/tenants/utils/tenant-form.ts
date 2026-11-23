const COMMON_TIMEZONES = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Europe/Paris',
  'Asia/Dubai',
  'Asia/Singapore',
  'Asia/Manila',
  'Asia/Tokyo',
  'Australia/Sydney'
] as const

export function resolveLocalTimezone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  } catch {
    return 'UTC'
  }
}

export function buildTimezoneOptions(preferredTimezone: string) {
  const supportedTimezones =
    typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : Array.from(COMMON_TIMEZONES)

  return Array.from(new Set([preferredTimezone, ...COMMON_TIMEZONES, ...supportedTimezones])).filter(Boolean)
}

export function buildSlugPreview(name: string, slug: string) {
  const explicitSlug = slug.trim()

  if (explicitSlug) return explicitSlug

  const generatedSlug = name
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-')

  return generatedSlug || 'company-slug'
}
