'use client'

import { FormControl, InputLabel, MenuItem, Select } from '@mui/material'

import type { JournalNumberResetInterval } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

type Props = {
  setResetInterval: (value: JournalNumberResetInterval | '') => void
  disabled: boolean
}

// Dropdown options for journal number reset intervals
const resetIntervals = [
  {
    label: 'All Intervals',
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

function TransactionSeriesFilter({ setResetInterval, disabled }: Props) {
  return (
    <FormControl fullWidth>
      <InputLabel id='reset-interval-label'>Reset Interval</InputLabel>
      <Select
        label='Reset Interval'
        defaultValue=''
        id='reset-interval'
        labelId='reset-interval-label'
        disabled={disabled}
        onChange={event => setResetInterval(event.target.value as JournalNumberResetInterval | '')}
      >
        {resetIntervals.map(interval => (
          <MenuItem key={interval.value} value={interval.value}>
            {interval.label}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  )
}

export default TransactionSeriesFilter
