import type { Dispatch, SetStateAction } from 'react'

import TextField from '@mui/material/TextField'
import InputAdornment from '@mui/material/InputAdornment'

// Props for this function
interface FundSourceSearchProps {
  setSearchText: Dispatch<SetStateAction<string>>
  disabled: boolean
}

function FundSourceSearch({ setSearchText, disabled }: FundSourceSearchProps) {
  return (
    <TextField
      fullWidth
      sx={{ flex: 2 }}
      disabled={disabled}
      placeholder='Search by fund source name...'
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

export default FundSourceSearch
