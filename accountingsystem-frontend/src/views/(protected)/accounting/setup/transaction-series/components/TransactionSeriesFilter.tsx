'use client'

import type { Dispatch, SetStateAction } from 'react'

import { FormControl, InputLabel, MenuItem, Select, Stack } from '@mui/material'

import type {
  JournalNumberResetInterval,
  JournalNumberStatus
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

interface TransactionSeriesFilterProps {
  setStatus: Dispatch<SetStateAction<JournalNumberStatus | ''>>
  setResetInterval: Dispatch<SetStateAction<JournalNumberResetInterval | ''>>
  disabled: boolean
}

// Dropdown options for journal number status
const statuses = [
  {
    label: 'All Types',
    value: ''
  },
  {
    label: 'Active',
    value: 'active'
  },
  {
    label: 'Inactive',
    value: 'inactive'
  }
]

// Dropdown options for journal number reset intervals
const resetIntervals = [
  {
    label: 'All Types',
    value: ''
  },
  {
    label: 'Daily',
    value: 'daily'
  },
  {
    label: 'Weekly',
    value: 'weekly'
  },
  {
    label: 'Monthly',
    value: 'monthly'
  },
  {
    label: 'Yearly',
    value: 'yearly'
  }
]

/**
 * Lightweight filter row for the transaction-series table.
 */
function TransactionSeriesFilter({ setStatus, setResetInterval, disabled }: TransactionSeriesFilterProps) {
  return (
    <Stack direction='row' spacing={5} alignItems='center' sx={{ flex: 1 }}>
      {/* Journal Number Status Filter */}
      <FormControl fullWidth>
        <InputLabel id='status-label'>Status</InputLabel>
        <Select
          label='Status'
          defaultValue=''
          id='status'
          labelId='status-label'
          disabled={disabled}
          onChange={event => setStatus(event.target.value as JournalNumberStatus)}
        >
          {statuses.map(status => (
            <MenuItem key={status.label} value={status.value}>
              {status.label}
            </MenuItem>
          ))}
        </Select>
      </FormControl>

      {/* Journal Number Reset Interval Filter */}
      <FormControl fullWidth>
        <InputLabel id='reset-interval-label'>Reset Interval</InputLabel>
        <Select
          label='Reset Interval'
          defaultValue=''
          id='reset-interval'
          labelId='reset-interval-label'
          disabled={disabled}
          onChange={event => setResetInterval(event.target.value as JournalNumberResetInterval)}
        >
          {resetIntervals.map(interval => (
            <MenuItem key={interval.label} value={interval.value}>
              {interval.label}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
    </Stack>
  )
}

export default TransactionSeriesFilter
