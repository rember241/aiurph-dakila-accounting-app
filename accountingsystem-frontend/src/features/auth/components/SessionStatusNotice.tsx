'use client'

import { useEffect, useState } from 'react'

import { useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'

import { me } from '@/features/auth/api/auth.api'
import { SESSION_STATUS_EVENT, type SessionStatus } from '@/features/auth/model/session-status'

export default function SessionStatusNotice() {
  const [status, setStatus] = useState<SessionStatus>('available')
  const [checking, setChecking] = useState(false)
  const client = useQueryClient()

  useEffect(() => {
    const handleStatus = (event: Event) => {
      const next = (event as CustomEvent<SessionStatus>).detail

      // A successful unrelated request doesn't prove the session is valid.
      setStatus(previous => previous === 'expired' && next === 'available' ? previous : next)
    }

    window.addEventListener(SESSION_STATUS_EVENT, handleStatus)

    return () => window.removeEventListener(SESSION_STATUS_EVENT, handleStatus)
  }, [])

  async function retry() {
    setChecking(true)

    try {
      const session = await me()

      if (session.user) {
        client.setQueryData(['auth', 'me'], session)
        setStatus('available')
        await client.invalidateQueries({ predicate: query => query.state.status === 'error' })
      } else {
        setStatus('expired')
      }
    } catch {
      // The API client reports availability and authentication separately.
    } finally {
      setChecking(false)
    }
  }

  if (status === 'available') return null

  return (
    <Alert
      severity={status === 'expired' ? 'warning' : 'info'}
      role='status'
      sx={{ position: 'fixed', bottom: 24, left: 24, right: 24, zIndex: 1500, boxShadow: 3 }}
      action={status === 'expired'
        ? <Button color='inherit' href='/login'>Sign in</Button>
        : <Button color='inherit' disabled={checking} onClick={retry}>{checking ? 'Checking…' : 'Try again'}</Button>}
    >
      {status === 'expired'
        ? 'Your session has expired. Sign in again to continue.'
        : 'The server is temporarily unavailable. Your page is still open. Check the connection before trying to save again.'}
    </Alert>
  )
}
