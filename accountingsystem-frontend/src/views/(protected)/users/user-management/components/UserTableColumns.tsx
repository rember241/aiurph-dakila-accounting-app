import { useState } from 'react'

import type { GridColDef } from '@mui/x-data-grid'

import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import IconButton from '@mui/material/IconButton'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Menu from '@mui/material/Menu'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import {
  getUserRoleScopeLabel,
  summarizeUserOrgUnits,
  type UserRecord
} from '@/features/(protected)/users/user-management/model/user-management.types'

function formatLastActive(iso: string) {
  const timestamp = new Date(iso).getTime()

  if (Number.isNaN(timestamp)) return '-'

  const diffMs = Date.now() - timestamp
  const diffMinutes = Math.floor(diffMs / (1000 * 60))

  if (diffMinutes < 1) return 'Just now'
  if (diffMinutes < 60) return `${diffMinutes} min ago`

  const diffHours = Math.floor(diffMinutes / 60)

  if (diffHours < 24) return `${diffHours} hr ago`

  const diffDays = Math.floor(diffHours / 24)

  if (diffDays < 30) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`

  return new Date(iso).toLocaleDateString()
}

type UserTableColumnsOptions = {
  onView: (user: UserRecord) => void
  onEdit: (user: UserRecord) => void
  onToggleStatus: (user: UserRecord) => void
  onDelete: (user: UserRecord) => void
  canEditUsers?: boolean
  canDeleteUsers?: boolean
}

function UserSettingsMenu({
  row,
  onView,
  onEdit,
  onToggleStatus,
  onDelete,
  canEditUsers = true,
  canDeleteUsers = true
}: { row: UserRecord } & UserTableColumnsOptions) {
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null)
  const open = Boolean(anchorEl)

  return (
    <>
      <Tooltip title='Settings'>
        <IconButton size='small' onClick={event => setAnchorEl(event.currentTarget)}>
          <i className='bx bx-cog' />
        </IconButton>
      </Tooltip>

      <Menu anchorEl={anchorEl} open={open} onClose={() => setAnchorEl(null)}>
        <MenuItem
          onClick={() => {
            onView(row)
            setAnchorEl(null)
          }}
        >
          <ListItemIcon>
            <i className='bx bx-show' />
          </ListItemIcon>
          <ListItemText>View profile</ListItemText>
        </MenuItem>

        {canEditUsers ? (
          <MenuItem
            disabled={!row.canEdit}
            onClick={() => {
              if (!row.canEdit) return

              onEdit(row)
              setAnchorEl(null)
            }}
          >
            <ListItemIcon>
              <i className='bx bx-edit' />
            </ListItemIcon>
            <ListItemText>Edit user</ListItemText>
          </MenuItem>
        ) : null}

        {canEditUsers && !row.isOwner ? (
          <MenuItem
            disabled={!row.canEdit}
            onClick={() => {
              if (!row.canEdit) return

              onToggleStatus(row)
              setAnchorEl(null)
            }}
          >
            <ListItemIcon>
              <i className={row.status === 'enabled' ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt'} />
            </ListItemIcon>
            <ListItemText>{row.status === 'enabled' ? 'Deactivate user' : 'Activate user'}</ListItemText>
          </MenuItem>
        ) : null}

        {canDeleteUsers && row.canDelete ? (
          <MenuItem
            onClick={() => {
              onDelete(row)
              setAnchorEl(null)
            }}
          >
            <ListItemIcon>
              <i className='bx bx-trash' />
            </ListItemIcon>
            <ListItemText>Delete user</ListItemText>
          </MenuItem>
        ) : null}
      </Menu>
    </>
  )
}

export function getUserTableColumns({
  onView,
  onEdit,
  onToggleStatus,
  onDelete,
  canEditUsers = true,
  canDeleteUsers = true
}: UserTableColumnsOptions): GridColDef<UserRecord>[] {
  return [
    {
      field: 'fullName',
      headerName: 'User',
      minWidth: 250,
      flex: 1,
      sortable: true,
      renderCell: ({ row }) => (
        <Stack direction='row' spacing={1.5} alignItems='center' sx={{ minWidth: 0, width: '100%', height: '100%' }}>
          <Avatar src={row.avatarUrl} alt={row.fullName} sx={{ width: 38, height: 38 }}>
            {row.fullName
              .split(' ')
              .map(chunk => chunk[0])
              .join('')
              .slice(0, 2)
              .toUpperCase()}
          </Avatar>
          <Stack spacing={0.2} sx={{ minWidth: 0 }}>
            <Typography variant='body2' sx={{ fontWeight: 600, lineHeight: 1.25 }} noWrap>
              {row.fullName}
              {row.isOwner ? ' (Owner)' : ''}
            </Typography>
            <Typography variant='caption' color='text.secondary' sx={{ lineHeight: 1.2 }} noWrap>
              {row.email}
            </Typography>
          </Stack>
        </Stack>
      )
    },
    {
      field: 'role',
      headerName: 'Roles',
      minWidth: 180,
      flex: 0.8,
      sortable: true
    },
    {
      field: 'primaryOrgUnitLabel',
      headerName: 'Primary Org Unit',
      minWidth: 220,
      flex: 0.8,
      sortable: true,
      renderCell: ({ row }) => (
        <Stack sx={{ width: '100%', height: '100%', justifyContent: 'center', alignItems: 'flex-start' }} spacing={0.4}>
          <Typography variant='body2' sx={{ fontWeight: 500 }} noWrap>
            {row.primaryOrgUnit?.name ?? 'Not assigned'}
          </Typography>
          <Typography variant='caption' color='text.secondary' noWrap>
            {row.primaryOrgUnit ? getOrgUnitTypeLabel(row.primaryOrgUnit.type) : 'Add a primary org unit'}
          </Typography>
        </Stack>
      )
    },
    {
      field: 'operatingScopeLabel',
      headerName: 'Operational Scope',
      minWidth: 230,
      flex: 0.95,
      sortable: true,
      renderCell: ({ row }) => (
        <Stack sx={{ width: '100%', height: '100%', justifyContent: 'center', alignItems: 'flex-start' }} spacing={0.6}>
          <Chip size='small' label={getUserRoleScopeLabel(row.roleAssignmentScope)} color='default' variant='tonal' />
          <Typography variant='caption' color='text.secondary' noWrap>
            {row.roleAssignmentScope === 'company'
              ? 'Across all company units'
              : row.roleAssignmentScope === 'primary_org_unit'
                ? (row.primaryOrgUnit?.name ?? 'Primary org unit not assigned')
                : summarizeUserOrgUnits(row.scopeOrgUnits, 'No scoped org units')}
          </Typography>
        </Stack>
      )
    },
    {
      field: 'status',
      headerName: 'Status',
      minWidth: 140,
      sortable: true,
      renderCell: ({ row }) => (
        <Chip
          size='small'
          label={row.status === 'enabled' ? 'Active' : 'Inactive'}
          icon={<i className={row.status === 'enabled' ? 'bx bx-lock-open-alt' : 'bx bx-lock-alt'} />}
          color={row.status === 'enabled' ? 'success' : 'error'}
          variant='tonal'
        />
      )
    },
    {
      field: 'accessLevel',
      headerName: 'Access',
      minWidth: 130,
      sortable: true,
      renderCell: ({ row }) => (
        <Chip
          size='small'
          label={row.accessLevel === 'write' ? 'Write' : 'Read'}
          icon={<i className={row.accessLevel === 'write' ? 'bx bx-shield-quarter' : 'bx bx-low-vision'} />}
          color={row.accessLevel === 'write' ? 'primary' : 'info'}
          variant='tonal'
        />
      )
    },
    {
      field: 'lastActive',
      headerName: 'Last Active',
      minWidth: 150,
      align: 'center',
      headerAlign: 'center',
      cellClassName: 'users-last-active-cell',
      sortable: true,
      renderCell: ({ row }) => (
        <Box sx={{ width: '100%', height: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
          <Typography variant='body2' sx={{ textAlign: 'center' }}>
            {formatLastActive(row.lastActive)}
          </Typography>
        </Box>
      ),
      sortComparator: (a, b) => new Date(a).getTime() - new Date(b).getTime()
    },
    {
      field: 'actions',
      headerName: 'Actions',
      sortable: false,
      filterable: false,
      disableColumnMenu: true,
      minWidth: 130,
      align: 'center',
      headerAlign: 'center',
      cellClassName: 'users-actions-cell',
      renderCell: ({ row }) => (
        <Stack direction='row' sx={{ width: '100%', height: '100%', justifyContent: 'center', alignItems: 'center' }}>
          <UserSettingsMenu
            row={row}
            onView={onView}
            onEdit={onEdit}
            onToggleStatus={onToggleStatus}
            onDelete={onDelete}
            canEditUsers={canEditUsers}
            canDeleteUsers={canDeleteUsers}
          />
        </Stack>
      )
    }
  ]
}
