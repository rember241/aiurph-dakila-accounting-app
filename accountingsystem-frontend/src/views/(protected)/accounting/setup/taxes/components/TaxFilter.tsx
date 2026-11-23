import type { Dispatch, SetStateAction } from 'react'

import { FormControl, InputLabel, MenuItem, Select } from '@mui/material'

import type { TaxStatus } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

// Props for this functional component
interface TaxFilterProps {
  setTaxStatus: Dispatch<SetStateAction<TaxStatus | ''>>
  disabled: boolean
}

// Tax statuses
const taxStatus = [
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

function TaxFilter({ setTaxStatus, disabled }: TaxFilterProps) {
  return (
    <FormControl sx={{ flex: 1 }} fullWidth>
      <InputLabel id='tax-status-label'>Status</InputLabel>
      <Select
        label='Status'
        defaultValue=''
        id='tax-status'
        disabled={disabled}
        labelId='tax-status-label'
        onChange={event => setTaxStatus(event.target.value as TaxStatus)}
      >
        {taxStatus.map(status => (
          <MenuItem key={status.label} value={status.value}>
            {status.label}
          </MenuItem>
        ))}
      </Select>
    </FormControl>
  )
}

export default TaxFilter
