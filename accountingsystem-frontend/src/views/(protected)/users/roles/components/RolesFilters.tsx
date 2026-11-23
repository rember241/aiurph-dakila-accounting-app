import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'
import Box from '@mui/material/Box'

import CustomTextField from '@core/components/mui/TextField'

import type {
  RoleAccessLevel,
  RoleOrgUnitOption,
  RoleStatus
} from '@/features/(protected)/users/roles/model/roles.types'

type RolesFiltersProps = {
  searchValue: string
  onSearchChange: (value: string) => void
  statusValue: RoleStatus | ''
  onStatusChange: (value: RoleStatus | '') => void
  accessValue: RoleAccessLevel | ''
  onAccessChange: (value: RoleAccessLevel | '') => void
  orgUnitValue: string
  onOrgUnitChange: (value: string) => void
  orgUnitOptions: RoleOrgUnitOption[]
  onClearFilters: () => void
  onCreateRole: () => void
  canCreateRole?: boolean
}

function RolesFilters({
  searchValue,
  onSearchChange,
  statusValue,
  onStatusChange,
  accessValue,
  onAccessChange,
  orgUnitValue,
  onOrgUnitChange,
  orgUnitOptions,
  onClearFilters,
  onCreateRole,
  canCreateRole = true
}: RolesFiltersProps) {
  return (
    <Paper sx={{ mb: 3, p: 5 }}>
      <Stack spacing={3}>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} alignItems={{ xs: 'stretch', md: 'center' }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flex: 1 }}>
            <i className='bx bx-search' style={{ fontSize: 22 }} />
            <CustomTextField
              fullWidth
              value={searchValue}
              onChange={event => onSearchChange(event.target.value)}
              placeholder='Search by role name, key, or org unit'
            />
          </Box>

          <Stack direction='row' spacing={1.5} justifyContent={{ xs: 'flex-end', md: 'flex-start' }}>
            <Button
              variant='outlined'
              color='secondary'
              onClick={onClearFilters}
              startIcon={<i className='bx bx-reset' />}
            >
              Clear Filters
            </Button>
            {canCreateRole ? (
              <Button variant='contained' onClick={onCreateRole} startIcon={<i className='bx bx-plus-medical' />}>
                New Role
              </Button>
            ) : null}
          </Stack>
        </Stack>

        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
          <FormControl fullWidth size='small'>
            <InputLabel id='role-status-filter-label'>Status</InputLabel>
            <Select
              labelId='role-status-filter-label'
              label='Status'
              value={statusValue}
              onChange={event => onStatusChange(event.target.value as RoleStatus | '')}
            >
              <MenuItem value=''>All Statuses</MenuItem>
              <MenuItem value='enabled'>Active</MenuItem>
              <MenuItem value='disabled'>Inactive</MenuItem>
            </Select>
          </FormControl>

          <FormControl fullWidth size='small'>
            <InputLabel id='role-access-filter-label'>Access</InputLabel>
            <Select
              labelId='role-access-filter-label'
              label='Access'
              value={accessValue}
              onChange={event => onAccessChange(event.target.value as RoleAccessLevel | '')}
            >
              <MenuItem value=''>All Access</MenuItem>
              <MenuItem value='read'>Read</MenuItem>
              <MenuItem value='write'>Write</MenuItem>
            </Select>
          </FormControl>

          <FormControl fullWidth size='small'>
            <InputLabel id='role-org-unit-filter-label'>Org Unit</InputLabel>
            <Select
              labelId='role-org-unit-filter-label'
              label='Org Unit'
              value={orgUnitValue}
              onChange={event => onOrgUnitChange(event.target.value)}
            >
              <MenuItem value=''>All Org Units</MenuItem>
              <MenuItem value='__company__'>Company-wide</MenuItem>
              {orgUnitOptions.map(option => (
                <MenuItem key={option.id} value={option.id}>
                  {option.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Stack>
      </Stack>
    </Paper>
  )
}

export default RolesFilters
