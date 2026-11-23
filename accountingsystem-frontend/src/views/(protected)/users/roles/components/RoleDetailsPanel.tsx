import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomDrawer from '@components/app/CustomDrawer'

import { PERMISSION_LABELS } from '@/features/(protected)/users/permissions'
import { ROLE_PERMISSION_MODULES } from '@/features/(protected)/users/roles/model/role-access-control'
import { getRoleOrgUnitLabel, type RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'

type RoleDetailsPanelProps = {
  open: boolean
  role: RoleRecord | null
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

function RoleDetailsPanel({ open, role, onClose }: RoleDetailsPanelProps) {
  return (
    <CustomDrawer
      open={open}
      onClose={onClose}
      width={430}
      title='Role Details'
      subtitle='Role profile and assigned users'
    >
      {role ? (
        <Stack spacing={3} sx={{ p: 5 }}>
          <Stack spacing={1}>
            <Typography variant='h5'>{role.name}</Typography>
            <Typography variant='body2' color='text.secondary'>
              {role.description}
            </Typography>
            <Stack direction='row' spacing={1}>
              <Chip
                size='small'
                label={role.status === 'enabled' ? 'Active' : 'Inactive'}
                color={role.status === 'enabled' ? 'success' : 'error'}
                variant='tonal'
              />
              <Chip
                size='small'
                label={role.accessLevel === 'write' ? 'Write Access' : 'Read Access'}
                color={role.accessLevel === 'write' ? 'primary' : 'info'}
                variant='outlined'
              />
            </Stack>
          </Stack>

          <Divider />

          <Stack spacing={2}>
            <Field label='Role Key' value={role.key} />
            <Field label='Org Unit Context' value={getRoleOrgUnitLabel(role)} />
            <Field label='Permission Scope' value={role.permissionScope === 'all' ? 'All Pages' : 'Per Page'} />
            <Field label='Last Updated' value={new Date(role.lastUpdated).toLocaleString()} />
            <Field label='Member Count' value={String(role.membersCount)} />
          </Stack>

          <Divider />

          <Box>
            <Typography variant='subtitle2' sx={{ mb: 1 }}>
              Permissions
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              {role.permissions.map(permission => (
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

          <Divider />

          <Box>
            <Typography variant='subtitle2' sx={{ mb: 1 }}>
              Module Access
            </Typography>
            <Stack spacing={0.8}>
              {ROLE_PERMISSION_MODULES.map(module => {
                const selected = role.modulePermissions[module.key]?.length ?? 0

                if (selected === 0) return null

                return (
                  <Typography key={module.key} variant='body2' color='text.secondary'>
                    {module.label}: {selected} permission(s)
                  </Typography>
                )
              })}
            </Stack>
          </Box>

          <Divider />

          <Box>
            <Typography variant='subtitle2' sx={{ mb: 1 }}>
              Default Users
            </Typography>
            {role.members.length ? (
              <List dense disablePadding>
                {role.members.map(member => (
                  <ListItem key={member.id} disablePadding sx={{ py: 0.8 }}>
                    <ListItemText primary={member.fullName} secondary={member.email} />
                  </ListItem>
                ))}
              </List>
            ) : (
              <Typography variant='body2' color='text.secondary'>
                No default users assigned.
              </Typography>
            )}
          </Box>
        </Stack>
      ) : (
        <Box sx={{ p: 5 }}>
          <Typography variant='body2' color='text.secondary'>
            Select a role to view details.
          </Typography>
        </Box>
      )}
    </CustomDrawer>
  )
}

export default RoleDetailsPanel
