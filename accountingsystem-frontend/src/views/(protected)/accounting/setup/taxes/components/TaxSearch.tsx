'use client'

import type { Dispatch, SetStateAction } from 'react'

import TextField from '@mui/material/TextField'
import { InputAdornment } from '@mui/material'

interface TaxSearchProps {
  handleSearch: Dispatch<SetStateAction<string>>
  disabled: boolean
}

function TaxSearch({ handleSearch, disabled }: TaxSearchProps) {
  // TODO: Change setTimeout to debounce
  return (
    <TextField
      fullWidth
      sx={{ flex: 2 }}
      disabled={disabled}
      placeholder='Search by tax name...'
      onChange={event => setTimeout(() => handleSearch(event.target.value), 400)}
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

export default TaxSearch
