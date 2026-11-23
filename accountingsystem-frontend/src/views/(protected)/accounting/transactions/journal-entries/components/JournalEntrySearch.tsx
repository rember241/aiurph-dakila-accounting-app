'use client'

import type { Dispatch, SetStateAction } from 'react'

import { InputAdornment, TextField } from '@mui/material'

interface JournalSearchProps {
  setSearchText: Dispatch<SetStateAction<string>>
  disabled: boolean
}

function JournalEntrySearch({ setSearchText, disabled }: JournalSearchProps) {
  return (
    <TextField
      fullWidth
      sx={{ flex: 2 }}
      disabled={disabled}
      placeholder='Search by journal number, reference number, or note...'
      onChange={event => setSearchText(event.target.value)}
      slotProps={{
        input: {
          startAdornment: (
            <InputAdornment position='start'>
              <i className='bx bx-search' />
            </InputAdornment>
          )
        }
      }}
    />
  )
}

export default JournalEntrySearch
