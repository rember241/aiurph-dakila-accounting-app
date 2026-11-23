'use client'

/**
 * LocationPicker — SSR-safe wrapper around LocationPickerMap.
 *
 * Combines a search-as-you-type Nominatim autocomplete with an interactive
 * Leaflet map. The parent receives a single `LocationValue` via `onChange`.
 */

import { useEffect, useRef, useState } from 'react'

import dynamic from 'next/dynamic'

import { useQuery } from '@tanstack/react-query'

import Autocomplete from '@mui/material/Autocomplete'
import Box from '@mui/material/Box'
import CircularProgress from '@mui/material/CircularProgress'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomTextField from '@core/components/mui/TextField'

import type { LocationPickerMapProps } from './LocationPickerMap'

// ─── Dynamic import (no SSR) ──────────────────────────────────────────────────

const LocationPickerMap = dynamic<LocationPickerMapProps>(
  () => import('./LocationPickerMap'),
  {
    ssr: false,
    loading: () => (
      <Box sx={{ height: 260, display: 'flex', alignItems: 'center', justifyContent: 'center', bgcolor: 'action.hover', borderRadius: 1 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }
)

// ─── Types ────────────────────────────────────────────────────────────────────

export type LocationValue = {
  address: string
  latitude: number | null
  longitude: number | null
}

type NominatimResult = {
  place_id: number
  display_name: string
  lat: string
  lon: string
}

type LocationPickerProps = {
  value: LocationValue
  onChange: (value: LocationValue) => void
  disabled?: boolean
  error?: boolean
  helperText?: string
  mapHeight?: string | number
  searchCountryCodes?: string
}

// ─── Nominatim helpers ────────────────────────────────────────────────────────

async function searchNominatim(
  query: string,
  options: {
    signal?: AbortSignal
    countryCodes?: string
  } = {}
): Promise<NominatimResult[]> {
  const params = new URLSearchParams({
    format: 'json',
    q: query,
    limit: '8',
    addressdetails: '1'
  })

  if (options.countryCodes) {
    params.set('countrycodes', options.countryCodes)
  }

  const url = `https://nominatim.openstreetmap.org/search?${params.toString()}`

  const res = await fetch(url, {
    signal: options.signal,
    headers: {
      Accept: 'application/json',
      'Accept-Language': 'en'
    }
  })

  if (!res.ok) return []

  return res.json() as Promise<NominatimResult[]>
}

async function reverseGeocode(lat: number, lng: number): Promise<string | null> {
  const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`
  const res = await fetch(url, { headers: { Accept: 'application/json' } })

  if (!res.ok) return null

  const result = await res.json() as { display_name?: string }

  return result.display_name ?? null
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function LocationPicker({
  value,
  onChange,
  disabled = false,
  error = false,
  helperText,
  mapHeight = 260,
  searchCountryCodes
}: LocationPickerProps) {
  const [inputText, setInputText] = useState(value.address)
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const hasPin = value.latitude !== null && value.longitude !== null
  const position: [number, number] | null = hasPin ? [value.latitude!, value.longitude!] : null
  const normalizedQuery = inputText.trim()
  const canSearch = normalizedQuery.length >= 3 && !disabled

  // Sync input text when the address is updated externally (e.g. map click → reverse geocode)
  useEffect(() => {
    setInputText(value.address)
  }, [value.address])

  // Debounce the query before handing it to TanStack Query, so quick typing
  // does not send one geocoding request per keystroke.
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)

    if (!canSearch) {
      setDebouncedQuery('')

      return
    }

    debounceRef.current = setTimeout(() => {
      setDebouncedQuery(normalizedQuery)
    }, 300)

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [canSearch, normalizedQuery])

  const locationSearchQuery = useQuery({
    queryKey: ['location-search', searchCountryCodes ?? 'global', debouncedQuery],
    queryFn: ({ signal }) => searchNominatim(debouncedQuery, { signal, countryCodes: searchCountryCodes }),
    enabled: debouncedQuery.length >= 3 && !disabled,
    staleTime: 10 * 60_000,
    gcTime: 30 * 60_000,
    retry: false,
    placeholderData: previous => previous ?? []
  })

  const suggestions = locationSearchQuery.data ?? []
  const isSearching = canSearch && (locationSearchQuery.isFetching || debouncedQuery !== normalizedQuery)

  // ── Map click / drag → reverse geocode ──────────────────────────────────────

  const handleMapPick = async (lat: number, lng: number) => {
    onChange({ ...value, latitude: lat, longitude: lng })

    try {
      const display = await reverseGeocode(lat, lng)

      if (display) {
        onChange({ address: display, latitude: lat, longitude: lng })
      }
    } catch {
      // Keep the coordinates even if reverse geocoding fails.
    }
  }

  return (
    <Stack spacing={1.5}>
      {/* Search autocomplete */}
      <Autocomplete<NominatimResult, false, false, true>
        freeSolo
        options={suggestions}
        getOptionLabel={option => (typeof option === 'string' ? option : option.display_name)}
        inputValue={inputText}
        filterOptions={x => x}
        loading={isSearching}
        disabled={disabled}
        noOptionsText={inputText.trim().length < 3 ? 'Type at least 3 characters to search' : 'No locations found'}
        onInputChange={(_, newVal, reason) => {
          if (reason !== 'input') return

          setInputText(newVal)

          // Clear the saved pin while the user is editing so the form stays consistent
          if (newVal !== value.address) {
            onChange({ address: newVal, latitude: null, longitude: null })
          }
        }}
        onChange={(_, option) => {
          if (!option || typeof option === 'string') return

          onChange({
            address: option.display_name,
            latitude: parseFloat(option.lat),
            longitude: parseFloat(option.lon)
          })
        }}
        renderOption={(props, option) => (
          <Box component='li' {...props} key={option.place_id}>
            <Stack spacing={0.25}>
              <Typography variant='body2'>{option.display_name}</Typography>
            </Stack>
          </Box>
        )}
        renderInput={params => (
          <CustomTextField
            {...params}
            label='Location / Address'
            placeholder='Search an address or click the map to pin a location'
            error={error}
            helperText={helperText}
            InputProps={{
              ...params.InputProps,
              endAdornment: (
                <>
                  {isSearching ? <CircularProgress color='inherit' size={18} /> : null}
                  {params.InputProps.endAdornment}
                </>
              )
            }}
          />
        )}
      />

      {/* Map */}
      <Paper variant='outlined' sx={{ borderRadius: 1.5, overflow: 'hidden', position: 'relative' }}>
        <LocationPickerMap position={position} onPick={(lat, lng) => void handleMapPick(lat, lng)} height={mapHeight} />

        {/* "Click to pin" hint overlay — shown only when no pin is set */}
        {!hasPin && (
          <Box
            sx={{
              position: 'absolute',
              bottom: 8,
              left: '50%',
              transform: 'translateX(-50%)',
              zIndex: 1000,
              pointerEvents: 'none'
            }}
          >
            <Paper
              elevation={2}
              sx={{
                px: 1.5,
                py: 0.5,
                borderRadius: 5,
                bgcolor: 'background.paper',
                opacity: 0.9
              }}
            >
              <Stack direction='row' spacing={0.75} alignItems='center'>
                <i className='bx bx-map-pin' style={{ fontSize: 14 }} />
                <Typography variant='caption' color='text.secondary'>
                  Click the map or search an address to pin a location
                </Typography>
              </Stack>
            </Paper>
          </Box>
        )}
      </Paper>

      {/* Coordinates badge */}
      {hasPin && (
        <Stack direction='row' spacing={0.75} alignItems='center'>
          <i className='bx bx-map-pin' style={{ fontSize: 13, color: 'var(--mui-palette-success-main)' }} />
          <Typography variant='caption' color='text.secondary'>
            {value.latitude!.toFixed(6)}, {value.longitude!.toFixed(6)}
          </Typography>
        </Stack>
      )}
    </Stack>
  )
}
