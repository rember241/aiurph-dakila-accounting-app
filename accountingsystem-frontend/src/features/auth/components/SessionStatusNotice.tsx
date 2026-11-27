'use client'

import { useCallback, useEffect, useRef, useState } from 'react'

import { useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import LinearProgress from '@mui/material/LinearProgress'
import Typography from '@mui/material/Typography'

import { extendSession, me } from '@/features/auth/api/auth.api'
import { AUTH_QUERY_KEYS } from '@/features/auth/api/auth.queries'
import {
  SESSION_EXPIRY_EVENT,
  SESSION_STATUS_EVENT,
  type SessionIssue,
  type SessionWindow,
  type SessionStatus
} from '@/features/auth/model/session-status'
import { isLaravelUnauthorized } from '@/lib/axios/auth/laravel-browser-client'

const SESSION_RECHECK_INTERVAL_MS = 5_000

function signOut() {
  window.location.replace('/logout')
}

export default function SessionStatusNotice({
  initialSessionWindow
}: {
  initialSessionWindow: SessionWindow | null
}) {
  const [status, setStatus] = useState<SessionStatus>('available')
  const [sessionWindow, setSessionWindow] = useState(initialSessionWindow)
  const [secondsRemaining, setSecondsRemaining] = useState<number | null>(null)
  const [pending, setPending] = useState<'check' | 'extend' | null>(null)
  const [extensionFailed, setExtensionFailed] = useState(false)
  const requestInFlight = useRef(false)
  const client = useQueryClient()

  useEffect(() => {
    if (initialSessionWindow) {
      setSessionWindow(previous => (previous?.expiresAt ?? 0) > initialSessionWindow.expiresAt
        ? previous
        : initialSessionWindow)
    }
  }, [initialSessionWindow])

  const checkSession = useCallback(async () => {
    if (requestInFlight.current) return

    requestInFlight.current = true
    setPending('check')

    try {
      const session = await me()

      if (session.user) {
        client.setQueryData(AUTH_QUERY_KEYS.me, session)
        setStatus('available')
        void client.invalidateQueries({ predicate: query => query.state.status === 'error' })
      } else {
        setStatus('expired')
      }
    } catch (error) {
      setStatus(isLaravelUnauthorized(error) ? 'expired' : 'unavailable')
    } finally {
      requestInFlight.current = false
      setPending(null)
    }
  }, [client])

  useEffect(() => {
    const handleStatus = (event: Event) => {
      const next = (event as CustomEvent<SessionIssue>).detail

      if (next === 'expired') {
        void checkSession()

        return
      }

      setStatus(previous => previous === 'expired' ? previous : next)
    }

    const handleExpiry = (event: Event) => {
      const next = (event as CustomEvent<SessionWindow>).detail

      setSessionWindow(previous => (previous?.expiresAt ?? 0) > next.expiresAt ? previous : next)
      setExtensionFailed(false)
    }

    window.addEventListener(SESSION_STATUS_EVENT, handleStatus)
    window.addEventListener(SESSION_EXPIRY_EVENT, handleExpiry)

    return () => {
      window.removeEventListener(SESSION_STATUS_EVENT, handleStatus)
      window.removeEventListener(SESSION_EXPIRY_EVENT, handleExpiry)
    }
  }, [checkSession])

  useEffect(() => {
    if (status === 'expired') signOut()
  }, [status])

  useEffect(() => {
    if (status !== 'unavailable') return

    const recheck = () => {
      if (document.visibilityState === 'visible' && navigator.onLine) void checkSession()
    }

    const interval = window.setInterval(recheck, SESSION_RECHECK_INTERVAL_MS)

    window.addEventListener('online', recheck)
    document.addEventListener('visibilitychange', recheck)

    return () => {
      window.clearInterval(interval)
      window.removeEventListener('online', recheck)
      document.removeEventListener('visibilitychange', recheck)
    }
  }, [checkSession, status])

  useEffect(() => {
    if (!sessionWindow || status === 'expired') return

    const { expiresAt, warningSeconds } = sessionWindow

    let timer: number

    const tick = () => {
      window.clearTimeout(timer)

      const remaining = expiresAt - Date.now()

      setSecondsRemaining(remaining <= warningSeconds * 1000
        ? Math.max(0, Math.ceil(remaining / 1000))
        : null)

      // Check after Laravel's one-second timestamp boundary. Another request
      // may have extended the session while this tab was idle.
      if (remaining <= -1000) {
        if (status === 'available' && !pending) void checkSession()

        return
      }

      timer = window.setTimeout(tick, remaining > warningSeconds * 1000
        ? Math.min(remaining - warningSeconds * 1000, 2_147_483_647)
        : 1000)
    }

    tick()
    document.addEventListener('visibilitychange', tick)

    return () => {
      window.clearTimeout(timer)
      document.removeEventListener('visibilitychange', tick)
    }
  }, [checkSession, pending, sessionWindow, status])

  async function handleExtend() {
    if (requestInFlight.current) return

    requestInFlight.current = true
    setPending('extend')
    setExtensionFailed(false)

    try {
      await extendSession()
      setStatus('available')
    } catch (error) {
      if (isLaravelUnauthorized(error)) setStatus('expired')
      else setExtensionFailed(true)
    } finally {
      requestInFlight.current = false
      setPending(null)
    }
  }

  const countdown = secondsRemaining ?? 0
  const countdownText = `${Math.floor(countdown / 60)}:${String(countdown % 60).padStart(2, '0')}`

  return (
    <>
      {status === 'unavailable' && secondsRemaining === null && (
        <Alert
          severity='info'
          role='status'
          sx={{ position: 'fixed', bottom: 24, left: 24, right: 24, zIndex: 1500, boxShadow: 3 }}
          action={(
            <Button color='inherit' disabled={!!pending} onClick={checkSession}>
              {pending ? 'Checking…' : 'Try again'}
            </Button>
          )}
        >
          The server is temporarily unavailable. Your page is still open. Check the connection before trying to save again.
        </Alert>
      )}
      <Dialog
        open={secondsRemaining !== null && status !== 'expired'}
        aria-labelledby='session-expiry-title'
        aria-describedby='session-expiry-description'
        fullWidth
        maxWidth='xs'
        disableEscapeKeyDown
      >
        <DialogTitle id='session-expiry-title'>Your session is about to expire</DialogTitle>
        <DialogContent>
          <DialogContentText id='session-expiry-description'>
            Extend your session to keep working, or sign out.
          </DialogContentText>
          <Typography
            variant='h2'
            color={countdown <= 30 ? 'error.main' : 'text.primary'}
            role='timer'
            aria-label={`${countdownText} remaining`}
            sx={{ mt: 3, mb: 2, fontVariantNumeric: 'tabular-nums' }}
          >
            {countdownText}
          </Typography>
          <LinearProgress
            variant='determinate'
            value={Math.min(100, (countdown / (sessionWindow?.warningSeconds ?? 1)) * 100)}
            color={countdown <= 30 ? 'error' : 'primary'}
            aria-label='Time remaining in session'
          />
          {(extensionFailed || status === 'unavailable') && (
            <Alert severity='warning' sx={{ mt: 3 }}>
              {status === 'unavailable'
                ? 'The server is unavailable. Your page will stay open while we check the connection.'
                : 'We couldn’t extend your session. Please try again.'}
            </Alert>
          )}
          {pending === 'check' && <Typography role='status' sx={{ mt: 3 }}>Checking your session…</Typography>}
        </DialogContent>
        <DialogActions>
          <Button color='inherit' onClick={signOut}>Sign out</Button>
          <Button variant='contained' disabled={!!pending} onClick={handleExtend} autoFocus>
            {pending === 'extend' ? 'Extending…' : 'Extend session'}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
