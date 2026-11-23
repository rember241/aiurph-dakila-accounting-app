import type { Dispatch, SetStateAction } from 'react'

import { FormControl, InputLabel, MenuItem, Select } from '@mui/material'

import type { FundSourceStatus } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'

// Props for this function
interface FundSourceFilterProps {
  setFundStatus: Dispatch<SetStateAction<FundSourceStatus | ''>>
  disabled: boolean
}

// Fund source statuses
const fundStatus = [
  {
    label: 'All Statuses',
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

function FundSourceFilter({ setFundStatus, disabled }: FundSourceFilterProps) {
  return (
    <FormControl sx={{ flex: 1 }} fullWidth>
      <InputLabel id='fund-status-label'>Status</InputLabel>
      <Select
        label='Status'
        defaultValue=''
        id='fund-status'
        disabled={disabled}
        labelId='fund-status-label'
        onChange={event => setFundStatus(event.target.value as FundSourceStatus)}
      >
        {fundStatus.map(status => (
          <MenuItem key={status.label} value={status.value}>
            {status.label}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  )
}

export default FundSourceFilter
