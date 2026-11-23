import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomDrawer from '@components/app/CustomDrawer'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import { PERMISSION_LABELS } from '@/features/(protected)/users/permissions'
import {
  getUserRoleScopeLabel,
  summarizeUserOrgUnits,
  type UserRecord
} from '@/features/(protected)/users/user-management/model/user-management.types'

type UserProfilePanelProps = {
  open: boolean
  user: UserRecord | null
  onClose: () => void
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <Box>
      <Typography variant='caption' color='text.secondary'>
        {label}
      </Typography>
      <Typography variant='body2' sx={{ fontWeight: 500 }}>
        {value}
      </Typography>
    </Box>
  )
}

function UserProfilePanel({ open, user, onClose }: UserProfilePanelProps) {
  return (
    <CustomDrawer open={open} onClose={onClose} width={420} title='User Profile' subtitle='View user details'>
      {user ? (
        <Stack spacing={3} sx={{ p: 5 }}>
          <Stack spacing={2} alignItems='center'>
            <Avatar src={user.avatarUrl} alt={user.fullName} sx={{ width: 72, height: 72 }}>
              {user.fullName
                .split(' ')
                .map(chunk => chunk[0])
                .join('')
                .slice(0, 2)
                .toUpperCase()}
            </Avatar>
            <Stack spacing={0.5} alignItems='center'>
              <Typography variant='h5'>{user.fullName}</Typography>
              <Typography variant='body2' color='text.secondary'>
                {user.email}
              </Typography>
            </Stack>
            <Stack direction='row' spacing={1}>
              <Chip
                size='small'
                label={user.status === 'enabled' ? 'Active' : 'Inactive'}
                color={user.status === 'enabled' ? 'success' : 'error'}
                variant='tonal'
              />
              <Chip
                size='small'
                label={user.accessLevel === 'write' ? 'Write Access' : 'Read Access'}
                color={user.accessLevel === 'write' ? 'primary' : 'info'}
                variant='outlined'
              />
            </Stack>
          </Stack>

          <Divider />

          <Stack spacing={2}>
            <Field label='Company' value='Current company' />
            <Field label='Roles' value={user.roleNames.join(', ') || 'No roles assigned'} />
            <Field label='Primary Org Unit' value={user.primaryOrgUnit?.name ?? 'Not assigned'} />
            <Field label='Additional Org Units' value={summarizeUserOrgUnits(user.additionalOrgUnits)} />
            <Field label='Operational Scope' value={user.operatingScopeLabel} />
            <Field label='Last Active' value={new Date(user.lastActive).toLocaleString()} />
            <Field label='User ID' value={user.id} />
          </Stack>

          <Divider />

          <Box>
            <Typography variant='caption' color='text.secondary' sx={{ display: 'block', mb: 1.2 }}>
              Role Assignments
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              {user.roles.map(role => (
                <Chip
                  key={role.id}
                  size='small'
                  label={role.name}
                  color={role.accessLevel === 'write' ? 'primary' : 'info'}
                  variant='tonal'
                />
              ))}
            </Stack>
          </Box>

          <Divider />

          <Box>
            <Typography variant='caption' color='text.secondary' sx={{ display: 'block', mb: 1.2 }}>
              Org Unit Coverage
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              {user.primaryOrgUnit ? (
                <Chip
                  size='small'
                  label={`${user.primaryOrgUnit.name} (${getOrgUnitTypeLabel(user.primaryOrgUnit.type)})`}
                  color='primary'
                  variant='tonal'
                />
              ) : (
                <Chip size='small' label='Primary org unit not assigned' variant='outlined' color='warning' />
              )}
              {user.additionalOrgUnits.map(orgUnit => (
                <Chip
                  key={orgUnit.id}
                  size='small'
                  label={`${orgUnit.name} (${getOrgUnitTypeLabel(orgUnit.type)})`}
                  color='default'
                  variant='outlined'
                />
              ))}
            </Stack>
          </Box>

          <Divider />

          <Box>
            <Typography variant='caption' color='text.secondary' sx={{ display: 'block', mb: 1.2 }}>
              Role Scope
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              <Chip size='small' label={getUserRoleScopeLabel(user.roleAssignmentScope)} color='info' variant='tonal' />
              {user.roleAssignmentScope === 'selected_org_units'
                ? user.scopeOrgUnits.map(orgUnit => (
                    <Chip
                      key={orgUnit.id}
                      size='small'
                      label={`${orgUnit.name} (${getOrgUnitTypeLabel(orgUnit.type)})`}
                      color='default'
                      variant='outlined'
                    />
                  ))
                : null}
              {user.roleAssignmentScope === 'primary_org_unit' && user.primaryOrgUnit ? (
                <Chip
                  size='small'
                  label={`${user.primaryOrgUnit.name} (${getOrgUnitTypeLabel(user.primaryOrgUnit.type)})`}
                  color='default'
                  variant='outlined'
                />
              ) : null}
            </Stack>
          </Box>

          <Divider />

          <Box>
            <Typography variant='caption' color='text.secondary' sx={{ display: 'block', mb: 1.2 }}>
              Permissions
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              {user.permissions.map(permission => (
                <Chip
                  key={permission}
                  size='small'
                  label={PERMISSION_LABELS[permission]}
                  variant='outlined'
                  color='default'
                />
              ))}
            </Stack>
          </Box>
        </Stack>
      ) : (
        <Box sx={{ p: 5 }}>
          <Typography variant='body2' color='text.secondary'>
            Select a user to view details.
          </Typography>
        </Box>
      )}
    </CustomDrawer>
  )
}

export default UserProfilePanel
