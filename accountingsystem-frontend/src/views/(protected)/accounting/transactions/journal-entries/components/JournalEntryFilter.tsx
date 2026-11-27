'use client'

import { FormControl, InputLabel, MenuItem, Select } from '@mui/material'

import type { JournalEntryStatus } from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'

type Props = {
  setJournalStatus: (value: JournalEntryStatus | '') => void
  disabled: boolean
}

// Dropdown options for journal status
const journalStatuses = [
  {
    label: 'All Statuses',
    value: ''
  },
  {
    label: 'Posted',
    value: 'posted'
  },
  {
    label: 'Draft',
    value: 'draft'
  },
  {
    label: 'Void',
    value: 'void'
  }
]

function JournalEntryFilter({ setJournalStatus, disabled }: Props) {
  return (
    <FormControl fullWidth>
      <InputLabel id='journal-status-label'>Status</InputLabel>
      <Select
        label='Status'
        defaultValue=''
        id='journal-status'
        labelId='journal-status-label'
        disabled={disabled}
        onChange={event => setJournalStatus(event.target.value as JournalEntryStatus | '')}
      >
        {journalStatuses.map(status => (
          <MenuItem key={status.value} value={status.value}>
            {status.label}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  )
}

export default JournalEntryFilter
