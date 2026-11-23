'use client'

import type { Dispatch, SetStateAction } from 'react'

import TextField from '@mui/material/TextField'
import { InputAdornment } from '@mui/material'

interface TransactionSeriesSearchProps {
  setSearchText: Dispatch<SetStateAction<string>>
  disabled: boolean
}

function TransactionSeriesSearch({ setSearchText, disabled }: TransactionSeriesSearchProps) {
  return (
    <TextField
      fullWidth
      sx={{ flex: 2 }}
      disabled={disabled}
      placeholder='Search by name or prefix...'
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

export default TransactionSeriesSearch
