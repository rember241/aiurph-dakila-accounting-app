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
  UserOrgUnitOption,
  UserRoleOption,
  UserAccessLevel,
  UserStatus
} from '@/features/(protected)/users/user-management/model/user-management.types'

type UserManagementFiltersProps = {
  searchValue: string
  onSearchChange: (value: string) => void
  statusValue: UserStatus | ''
  onStatusChange: (value: UserStatus | '') => void
  accessValue: UserAccessLevel | ''
  onAccessChange: (value: UserAccessLevel | '') => void
  roleValue: string
  onRoleChange: (value: string) => void
  primaryOrgUnitValue: string
  onPrimaryOrgUnitChange: (value: string) => void
  roleOptions: UserRoleOption[]
  orgUnitOptions: UserOrgUnitOption[]
  onClearFilters: () => void
  onCreateUser: () => void
  canCreateUser?: boolean
}

function UserManagementFilters({
  searchValue,
  onSearchChange,
  statusValue,
  onStatusChange,
  accessValue,
  onAccessChange,
  roleValue,
  onRoleChange,
  primaryOrgUnitValue,
  onPrimaryOrgUnitChange,
  roleOptions,
  orgUnitOptions,
  onClearFilters,
  onCreateUser,
  canCreateUser = true
}: UserManagementFiltersProps) {
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
              placeholder='Search by name, email, role, or org unit'
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
            {canCreateUser ? (
              <Button variant='contained' onClick={onCreateUser} startIcon={<i className='bx bx-user-plus' />}>
                New User
              </Button>
            ) : null}
          </Stack>
        </Stack>

        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
          <FormControl fullWidth size='small'>
            <InputLabel id='user-status-filter-label'>Status</InputLabel>
            <Select
              labelId='user-status-filter-label'
              label='Status'
              value={statusValue}
              onChange={event => onStatusChange(event.target.value as UserStatus | '')}
            >
              <MenuItem value=''>All Statuses</MenuItem>
              <MenuItem value='enabled'>Active</MenuItem>
              <MenuItem value='disabled'>Inactive</MenuItem>
            </Select>
          </FormControl>

          <FormControl fullWidth size='small'>
            <InputLabel id='user-access-filter-label'>Access</InputLabel>
            <Select
              labelId='user-access-filter-label'
              label='Access'
              value={accessValue}
              onChange={event => onAccessChange(event.target.value as UserAccessLevel | '')}
            >
              <MenuItem value=''>All Access</MenuItem>
              <MenuItem value='read'>Read</MenuItem>
              <MenuItem value='write'>Write</MenuItem>
            </Select>
          </FormControl>

          <FormControl fullWidth size='small'>
            <InputLabel id='user-role-filter-label'>Role</InputLabel>
            <Select
              labelId='user-role-filter-label'
              label='Role'
              value={roleValue}
              onChange={event => onRoleChange(event.target.value)}
            >
              <MenuItem value=''>All Roles</MenuItem>
              {roleOptions.map(role => (
                <MenuItem key={role.id} value={role.id}>
                  {role.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl fullWidth size='small'>
            <InputLabel id='user-primary-org-unit-filter-label'>Primary Org Unit</InputLabel>
            <Select
              labelId='user-primary-org-unit-filter-label'
              label='Primary Org Unit'
              value={primaryOrgUnitValue}
              onChange={event => onPrimaryOrgUnitChange(event.target.value)}
            >
              <MenuItem value=''>All Org Units</MenuItem>
              {orgUnitOptions.map(orgUnit => (
                <MenuItem key={orgUnit.id} value={orgUnit.id}>
                  {orgUnit.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Stack>
      </Stack>
    </Paper>
  )
}

export default UserManagementFilters
