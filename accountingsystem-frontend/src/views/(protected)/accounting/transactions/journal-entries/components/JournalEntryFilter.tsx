'use client'

import type { Dispatch, SetStateAction } from 'react'

import { FormControl, InputLabel, MenuItem, Select } from '@mui/material'

import type {
  JournalEntryStatus,
  JournalEntryType
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'

interface JournalFilterProps {
  setJournalType: Dispatch<SetStateAction<JournalEntryType | ''>>
  setJournalStatus: Dispatch<SetStateAction<JournalEntryStatus | ''>>
  disabled: boolean
}

// Dropdown options for journal type
const journalTypes = [
  {
    label: 'All Types',
    value: ''
  },
  {
    label: 'General',
    value: 'general'
  },
  {
    label: 'Adjusting',
    value: 'adjusting'
  },
  {
    label: 'Closing',
    value: 'closing'
  }
]

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

function JournalEntryFilter({ setJournalType, setJournalStatus, disabled }: JournalFilterProps) {
  return (
    <>
      {/* Journal Type Filter */}
      <FormControl fullWidth>
        <InputLabel id='journal-type-label'>Type</InputLabel>
        <Select
          label='Type'
          defaultValue=''
          id='journal-type'
          labelId='journal-type-label'
          disabled={disabled}
          onChange={event => setJournalType(event.target.value as JournalEntryType)}
        >
          {journalTypes.map(type => (
            <MenuItem key={type.label} value={type.value}>
              {type.label}
            </MenuItem>
          ))}
        </Select>
      </FormControl>

      {/* Journal Status Filter */}
      <FormControl fullWidth>
        <InputLabel id='journal-status-label'>Status</InputLabel>
        <Select
          label='Status'
          defaultValue=''
          id='journal-status'
          labelId='journal-status-label'
          disabled={disabled}
          onChange={event => setJournalStatus(event.target.value as JournalEntryStatus)}
        >
          {journalStatuses.map(status => (
            <MenuItem key={status.label} value={status.value}>
              {status.label}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
    </>
  )
}

export default JournalEntryFilter
