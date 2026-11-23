'use client'

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

import { useRouter } from 'next/navigation'

import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import CircularProgress from '@mui/material/CircularProgress'
import ClickAwayListener from '@mui/material/ClickAwayListener'
import Divider from '@mui/material/Divider'
import InputAdornment from '@mui/material/InputAdornment'
import OutlinedInput from '@mui/material/OutlinedInput'
import Paper from '@mui/material/Paper'
import Popper from '@mui/material/Popper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { alpha, useTheme } from '@mui/material/styles'

import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { useNavSearchQuery } from '@/features/navigation/api/nav-search.queries'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'

import type { NavSearchEntry, NavSearchSection } from '@/features/navigation/model/nav-search.types'

// ─── Constants ────────────────────────────────────────────────────────────────

const DEBOUNCE_MS = 350
const MAX_RESULTS = 40

const SECTION_COLORS: Record<NavSearchSection, 'primary' | 'success' | 'secondary' | 'warning' | 'info'> = {
  Dashboard: 'primary',
  Sales: 'success',
  Inventory: 'secondary',
  Accounting: 'warning',
  Users: 'info'
}

// ─── Keyboard key cap ─────────────────────────────────────────────────────────

function KbdKey({ children }: { children: React.ReactNode }) {
  return (
    <Box
      component='kbd'
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        px: 0.75,
        py: 0.2,
        borderRadius: 0.75,
        border: '1px solid',
        borderColor: 'divider',
        bgcolor: 'action.hover',
        color: 'text.secondary',
        fontFamily: 'monospace',
        fontSize: 11,
        lineHeight: 1.6,
        fontStyle: 'normal',
        userSelect: 'none'
      }}
    >
      {children}
    </Box>
  )
}

// ─── Shortcut hint (end adornment) ────────────────────────────────────────────

function ShortcutHint() {
  const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/i.test(navigator.platform)

  return (
    <Stack direction='row' spacing={0.5} alignItems='center' sx={{ flexShrink: 0 }}>
      <KbdKey>{isMac ? '⌘ Cmd' : 'Ctrl'}</KbdKey>
      <Typography variant='caption' color='text.disabled' sx={{ lineHeight: 1 }}>+</Typography>
      <KbdKey>P</KbdKey>
    </Stack>
  )
}

// ─── Section header ───────────────────────────────────────────────────────────

function SectionHeader({ label }: { label: string }) {
  return (
    <Typography
      variant='overline'
      color='text.disabled'
      sx={{ px: 2.5, pt: 1.5, pb: 0.5, display: 'block', fontSize: 10, letterSpacing: '0.1em' }}
    >
      {label}
    </Typography>
  )
}

// ─── Result row ───────────────────────────────────────────────────────────────

type ResultRowProps = {
  entry: NavSearchEntry
  highlighted: boolean
  onMouseEnter: () => void
  onClick: () => void
}

function ResultRow({ entry, highlighted, onMouseEnter, onClick }: ResultRowProps) {
  const theme = useTheme()
  const rowRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (highlighted && rowRef.current) {
      rowRef.current.scrollIntoView({ block: 'nearest' })
    }
  }, [highlighted])

  return (
    <Box
      ref={rowRef}
      onClick={onClick}
      onMouseEnter={onMouseEnter}
      sx={{
        display: 'flex',
        alignItems: 'center',
        gap: 1.5,
        px: 2,
        py: 1.25,
        mx: 1,
        borderRadius: 1.5,
        cursor: 'pointer',
        bgcolor: highlighted ? alpha(theme.palette.primary.main, 0.1) : 'transparent',
        transition: 'background-color 80ms',
        '&:hover': { bgcolor: alpha(theme.palette.primary.main, 0.07) }
      }}
    >
      {/* Icon bubble */}
      <Box
        sx={{
          width: 34,
          height: 34,
          borderRadius: 1.5,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          bgcolor: highlighted
            ? alpha(theme.palette.primary.main, 0.15)
            : 'action.selected',
          color: highlighted ? 'primary.main' : 'text.secondary'
        }}
      >
        <i className={`bx ${entry.icon}`} style={{ fontSize: 17 }} />
      </Box>

      {/* Label + description */}
      <Stack spacing={0.2} sx={{ flexGrow: 1, minWidth: 0 }}>
        <Typography variant='body2' fontWeight={600} noWrap color='text.primary'>
          {entry.label}
        </Typography>
        <Typography variant='caption' color='text.secondary' noWrap>
          {entry.description}
        </Typography>
      </Stack>

      {/* Section badge */}
      <Chip
        size='small'
        label={entry.section}
        color={SECTION_COLORS[entry.section as NavSearchSection] ?? 'default'}
        variant='tonal'
        sx={{ flexShrink: 0, fontSize: 10 }}
      />

      {/* Enter key hint when highlighted */}
      {highlighted && (
        <KbdKey>↵</KbdKey>
      )}
    </Box>
  )
}

// ─── Keyboard hint strip (bottom of dropdown) ─────────────────────────────────

function KeyboardHints() {
  return (
    <Stack
      direction='row'
      spacing={2}
      sx={{
        px: 2.5,
        py: 1,
        borderTop: '1px solid',
        borderColor: 'divider',
        bgcolor: 'action.hover'
      }}
    >
      {[
        { keys: ['↑', '↓'], label: 'Navigate' },
        { keys: ['↵'], label: 'Open' },
        { keys: ['Esc'], label: 'Close' }
      ].map(({ keys, label }) => (
        <Stack key={label} direction='row' spacing={0.5} alignItems='center'>
          {keys.map(k => <KbdKey key={k}>{k}</KbdKey>)}
          <Typography variant='caption' color='text.disabled' sx={{ fontSize: 10 }}>
            {label}
          </Typography>
        </Stack>
      ))}
    </Stack>
  )
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function GlobalSearch() {
  const router = useRouter()
  const access = useCurrentTenantAccess()

  const [query, setQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const [highlightedIndex, setHighlightedIndex] = useState(0)

  const containerRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const debouncedQuery = useDebouncedValue(query, DEBOUNCE_MS)
  const { data, isFetching } = useNavSearchQuery(debouncedQuery)

  // ─── Permission-filtered + grouped results ──────────────────────────────────

  const results = useMemo(() => {
    const all = data?.results ?? []

    return all
      .filter(entry =>
        entry.requiredAbilities.length === 0 ||
        entry.requiredAbilities.every(ab => access.can(ab))
      )
      .slice(0, MAX_RESULTS)
  }, [data?.results, access])

  const grouped = useMemo(() => {
    const map = new Map<string, NavSearchEntry[]>()

    for (const entry of results) {
      const bucket = map.get(entry.section) ?? []

      bucket.push(entry)
      map.set(entry.section, bucket)
    }

    return map
  }, [results])

  // ─── Navigation ─────────────────────────────────────────────────────────────

  const close = useCallback(() => {
    setIsOpen(false)
    setQuery('')
    setHighlightedIndex(0)
    inputRef.current?.blur()
  }, [])

  const navigate = useCallback(
    (entry: NavSearchEntry) => {
      router.push(entry.href)
      close()
    },
    [router, close]
  )

  // ─── Keyboard handler ───────────────────────────────────────────────────────

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault()
          setHighlightedIndex(i => Math.min(i + 1, results.length - 1))
          break
        case 'ArrowUp':
          e.preventDefault()
          setHighlightedIndex(i => Math.max(i - 1, 0))
          break

        case 'Enter': {
          e.preventDefault()
          const entry = results[highlightedIndex]

          if (entry) navigate(entry)
          break
        }

        case 'Escape':
          close()
          break
      }
    },
    [results, highlightedIndex, navigate, close]
  )

  // Reset highlight whenever filtered results change.
  useEffect(() => { setHighlightedIndex(0) }, [debouncedQuery])

  // ─── Global Cmd+P / Ctrl+P shortcut ────────────────────────────────────────

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'p') {
        e.preventDefault()

        if (isOpen) {
          close()
        } else {
          setIsOpen(true)
          setTimeout(() => inputRef.current?.focus(), 30)
        }
      }
    }

    window.addEventListener('keydown', handler)

    return () => window.removeEventListener('keydown', handler)
  }, [isOpen, close])

  // ─── Derived state ──────────────────────────────────────────────────────────

  const isEmpty = !isFetching && results.length === 0 && query.trim().length > 0
  const dropdownWidth = containerRef.current?.offsetWidth ?? 'auto'

  // Running flat index used during render — reset each render pass.
  let flatIndex = 0

  return (
    <ClickAwayListener onClickAway={close}>
      {/* Wrapper div is the Popper anchor */}
      <Box
        ref={containerRef}
        sx={{
          width: { xs: 44, sm: '100%' },
          maxWidth: { sm: 640 },
          minWidth: { sm: 360 }
        }}
      >
        {/* ── Search input ─────────────────────────────────────────────── */}
        <OutlinedInput
          inputRef={inputRef}
          fullWidth
          placeholder='Search pages and modules…'
          value={query}
          size='medium'
          autoComplete='off'
          onFocus={() => setIsOpen(true)}
          onChange={e => {
            setQuery(e.target.value)
            setIsOpen(true)
          }}
          onKeyDown={handleKeyDown}
          startAdornment={
            <InputAdornment position='start'>
              {isFetching
                ? <CircularProgress size={16} thickness={5} />
                : <i className='bx bx-search' style={{ fontSize: 18 }} />
              }
            </InputAdornment>
          }
          endAdornment={
            <InputAdornment position='end'>
              {query.length > 0 ? (
                <Box
                  component='button'
                  type='button'
                  onClick={() => { setQuery(''); inputRef.current?.focus() }}
                  sx={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: 20, height: 20, borderRadius: '50%',
                    border: 'none', bgcolor: 'action.selected',
                    color: 'text.secondary', cursor: 'pointer',
                    '&:hover': { bgcolor: 'action.focus' }
                  }}
                >
                  <i className='bx bx-x' style={{ fontSize: 14 }} />
                </Box>
              ) : (
                <ShortcutHint />
              )}
            </InputAdornment>
          }
          sx={{
            height: 44,
            borderRadius: 2,
            fontSize: 14,
            '& .MuiOutlinedInput-notchedOutline': {
              borderColor: 'divider'
            },
            '&:hover .MuiOutlinedInput-notchedOutline': {
              borderColor: 'primary.main'
            },
            '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
              borderColor: 'primary.main'
            },
            display: { xs: 'none', sm: 'flex' }
          }}
        />

        {/* Mobile icon-only button */}
        <Box
          component='button'
          type='button'
          onClick={() => { setIsOpen(true); setTimeout(() => inputRef.current?.focus(), 30) }}
          sx={{
            display: { xs: 'flex', sm: 'none' },
            alignItems: 'center', justifyContent: 'center',
            width: 44, height: 44,
            border: '1px solid', borderColor: 'divider',
            borderRadius: 2, bgcolor: 'transparent',
            color: 'text.secondary', cursor: 'pointer'
          }}
        >
          <i className='bx bx-search' style={{ fontSize: 20 }} />
        </Box>

        {/* ── Dropdown ─────────────────────────────────────────────────── */}
        <Popper
          open={isOpen}
          anchorEl={containerRef.current}
          placement='bottom-start'
          style={{ width: dropdownWidth, zIndex: 1300 }}
          modifiers={[{ name: 'offset', options: { offset: [0, 6] } }]}
        >
          <Paper
            elevation={8}
            sx={{
              borderRadius: 2,
              border: '1px solid',
              borderColor: 'divider',
              bgcolor: 'background.paper',
              overflow: 'hidden'
            }}
          >
            {/* Results list */}
            <Box sx={{ maxHeight: 420, overflowY: 'auto', py: 1 }}>
              {isEmpty ? (
                <Stack spacing={1} alignItems='center' sx={{ py: 5 }}>
                  <i className='bx bx-search-alt' style={{ fontSize: 32, opacity: 0.25 }} />
                  <Typography variant='body2' color='text.disabled'>
                    No pages found for &ldquo;{query}&rdquo;
                  </Typography>
                </Stack>
              ) : (
                Array.from(grouped.entries()).map(([section, entries], sectionIdx) => (
                  <Box key={section}>
                    {sectionIdx > 0 && <Divider sx={{ my: 0.5, mx: 2 }} />}
                    <SectionHeader label={section} />
                    {entries.map(entry => {
                      const idx = flatIndex++

                      return (
                        <ResultRow
                          key={entry.id}
                          entry={entry}
                          highlighted={highlightedIndex === idx}
                          onMouseEnter={() => setHighlightedIndex(idx)}
                          onClick={() => navigate(entry)}
                        />
                      )
                    })}
                  </Box>
                ))
              )}
            </Box>

            <KeyboardHints />
          </Paper>
        </Popper>
      </Box>
    </ClickAwayListener>
  )
}
