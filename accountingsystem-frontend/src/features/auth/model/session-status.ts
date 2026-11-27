export type SessionStatus = 'available' | 'unavailable' | 'expired'
export type SessionIssue = Exclude<SessionStatus, 'available'>

export const SESSION_STATUS_EVENT = 'accounting:session-status'

export function reportSessionStatus(status: SessionIssue) {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent(SESSION_STATUS_EVENT, { detail: status }))
  }
}

export const SESSION_EXPIRY_EVENT = 'accounting:session-expiry'

export type SessionWindow = {
  expiresAt: number
  warningSeconds: number
}

export function parseSessionWindow(expiresAtHeader: unknown, warningHeader: unknown): SessionWindow | null {
  if (typeof expiresAtHeader !== 'string' || typeof warningHeader !== 'string') return null

  const expiresAt = Number(expiresAtHeader) * 1000
  const warningSeconds = Number(warningHeader)

  if (!Number.isSafeInteger(expiresAt) || expiresAt <= 0) return null
  if (!Number.isSafeInteger(warningSeconds) || warningSeconds <= 0) return null

  return { expiresAt, warningSeconds }
}

export function reportSessionWindow(sessionWindow: SessionWindow) {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent(SESSION_EXPIRY_EVENT, { detail: sessionWindow }))
  }
}
