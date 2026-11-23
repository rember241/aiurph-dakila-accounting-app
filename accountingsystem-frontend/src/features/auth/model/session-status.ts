export type SessionStatus = 'available' | 'unavailable' | 'expired'
export const SESSION_STATUS_EVENT = 'accounting:session-status'

export function reportSessionStatus(status: SessionStatus) {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent(SESSION_STATUS_EVENT, { detail: status }))
  }
}
