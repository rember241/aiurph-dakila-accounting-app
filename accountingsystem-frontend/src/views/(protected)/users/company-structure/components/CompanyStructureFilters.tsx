import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Paper from '@mui/material/Paper'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'

import CustomTextField from '@core/components/mui/TextField'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type {
  OrgUnitStatus,
  OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

type CompanyStructureFiltersProps = {
  searchValue: string
  typeValue: OrgUnitType | ''
  statusValue: OrgUnitStatus | ''
  onSearchChange: (value: string) => void
  onTypeChange: (value: OrgUnitType | '') => void
  onStatusChange: (value: OrgUnitStatus | '') => void
  onReset: () => void
}

function CompanyStructureFilters({
  searchValue,
  typeValue,
  statusValue,
  onSearchChange,
  onTypeChange,
  onStatusChange,
  onReset
}: CompanyStructureFiltersProps) {
  return (
    <Paper sx={{ p: 4 }}>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} alignItems={{ xs: 'stretch', md: 'center' }}>
        <CustomTextField
          value={searchValue}
          onChange={event => onSearchChange(event.target.value)}
          placeholder='Search by name, code, location, or description'
          fullWidth
        />

        <FormControl sx={{ minWidth: { xs: '100%', md: 220 } }}>
          <InputLabel id='org-unit-type-filter-label'>Type</InputLabel>
          <Select
            labelId='org-unit-type-filter-label'
            value={typeValue}
            label='Type'
            onChange={event => onTypeChange(event.target.value as OrgUnitType | '')}
          >
            <MenuItem value=''>All types</MenuItem>
            {(['head_office', 'branch', 'department', 'team'] as const).map(option => (
              <MenuItem key={option} value={option}>
                {getOrgUnitTypeLabel(option)}
              </MenuItem>
            ))}
          </Select>
        </FormControl>

        <FormControl sx={{ minWidth: { xs: '100%', md: 200 } }}>
          <InputLabel id='org-unit-status-filter-label'>Status</InputLabel>
          <Select
            labelId='org-unit-status-filter-label'
            value={statusValue}
            label='Status'
            onChange={event => onStatusChange(event.target.value as OrgUnitStatus | '')}
          >
            <MenuItem value=''>All statuses</MenuItem>
            <MenuItem value='active'>Active</MenuItem>
            <MenuItem value='inactive'>Inactive</MenuItem>
          </Select>
        </FormControl>

        <Button variant='text' color='secondary' onClick={onReset}>
          Reset
        </Button>
      </Stack>
    </Paper>
  )
}

export default CompanyStructureFilters
