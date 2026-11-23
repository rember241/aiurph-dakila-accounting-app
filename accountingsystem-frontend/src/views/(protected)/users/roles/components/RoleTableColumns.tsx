import type { GridColDef } from '@mui/x-data-grid'

import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import IconButton from '@mui/material/IconButton'
import Stack from '@mui/material/Stack'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import {
  canDeleteRole,
  canEditRole,
  getRoleOrgUnitLabel,
  getRoleDeleteGuardReason,
  getRoleEditGuardReason
} from '@/features/(protected)/users/roles/model/roles.types'

import type { RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'

function formatLastUpdated(iso: string) {
  const value = new Date(iso)

  if (Number.isNaN(value.getTime())) return '-'

  return value.toLocaleDateString()
}

type RoleTableColumnsOptions = {
  onSettings: (role: RoleRecord) => void
  onDelete: (role: RoleRecord) => void
  canEditRoles?: boolean
  canDeleteRoles?: boolean
}

export function getRoleTableColumns({
  onSettings,
  onDelete,
  canEditRoles = true,
  canDeleteRoles = true
}: RoleTableColumnsOptions): GridColDef<RoleRecord>[] {
  const columns: GridColDef<RoleRecord>[] = [
    {
      field: 'name',
      headerName: 'Role',
      minWidth: 220,
      flex: 1,
      align: 'center',
      headerAlign: 'center',
      sortable: true,
      renderCell: ({ row }) => {
        const isDeletable = canDeleteRole(row)
        const isEditable = canEditRole(row)

        return (
          <Stack
            direction={'row'}
            sx={{ width: '100%', height: '100%', justifyContent: 'flex-start', alignItems: 'center' }}
            spacing={1}
          >
            {(!isDeletable || !isEditable) && <i className={'bx-shield-minus w-5 h-5'} />}
            <Typography variant='body2' sx={{ fontWeight: 600, textAlign: 'center' }} noWrap>
              {row.name}
            </Typography>
            <Chip
              size='small'
              label={row.roleType === 'system' ? 'System' : 'Custom'}
              color={row.roleType === 'system' ? 'secondary' : 'primary'}
              icon={row.roleType === 'system' ? <i className='bx bx-lock' /> : <i className='bx bx-user' />}
              variant='tonal'
            />
          </Stack>
        )
      }
    },
    {
      field: 'orgUnit',
      headerName: 'Org Unit',
      minWidth: 170,
      flex: 0.8,
      align: 'center',
      headerAlign: 'center',
      sortable: true,
      renderCell: ({ row }) => (
        <Stack spacing={0.25} sx={{ width: '100%', minWidth: 0, alignItems: 'center' }}>
          <Typography variant='body2' noWrap sx={{ maxWidth: '100%', textAlign: 'center' }}>
            {getRoleOrgUnitLabel(row)}
          </Typography>
          {row.orgUnit ? (
            <Typography variant='caption' color='text.secondary' noWrap sx={{ maxWidth: '100%', textAlign: 'center' }}>
              {getOrgUnitTypeLabel(row.orgUnit.type)}
            </Typography>
          ) : null}
        </Stack>
      )
    },
    {
      field: 'memberCount',
      headerName: 'Members',
      minWidth: 120,
      align: 'center',
      headerAlign: 'center',
      cellClassName: 'roles-members-cell',
      sortable: false,
      renderCell: ({ row }) => (
        <Box
          sx={{ width: '100%', height: '100%', display: 'flex', justifyContent: 'flex-start', alignItems: 'center' }}
        >
          <Typography variant='body2' sx={{ textAlign: 'left' }}>
            {row.membersCount}
          </Typography>
        </Box>
      )
    },
    {
      field: 'status',
      headerName: 'Status',
      minWidth: 130,
      align: 'center',
      headerAlign: 'center',
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
      align: 'center',
      headerAlign: 'center',
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
      field: 'lastUpdated',
      headerName: 'Last Updated',
      minWidth: 150,
      align: 'center',
      headerAlign: 'center',
      cellClassName: 'roles-last-updated-cell',
      sortable: true,
      renderCell: ({ row }) => (
        <Box sx={{ width: '100%', height: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
          <Typography variant='body2' sx={{ textAlign: 'center' }}>
            {formatLastUpdated(row.lastUpdated)}
          </Typography>
        </Box>
      ),
      sortComparator: (a, b) => new Date(a).getTime() - new Date(b).getTime()
    }
  ]

  if (!canEditRoles && !canDeleteRoles) {
    return columns
  }

  return [
    ...columns,
    {
      field: 'actions',
      headerName: 'Actions',
      sortable: false,
      filterable: false,
      disableColumnMenu: true,
      minWidth: 130,
      align: 'center',
      headerAlign: 'center',
      cellClassName: 'roles-actions-cell',
      renderCell: ({ row }) => {
        const isDeletable = canDeleteRoles && canDeleteRole(row)
        const deleteGuardReason = getRoleDeleteGuardReason(row)
        const isEditable = canEditRoles && canEditRole(row)
        const editGuardReason = getRoleEditGuardReason(row)

        return (
          <Stack direction='row' sx={{ width: '100%', height: '100%', justifyContent: 'center', alignItems: 'center' }}>
            {canEditRoles ? (
              <Tooltip title={isEditable ? 'Settings' : (editGuardReason ?? 'Role settings unavailable')}>
                <span>
                  <IconButton size='small' disabled={!isEditable} onClick={() => onSettings(row)}>
                    <i className='bx bx-cog' />
                  </IconButton>
                </span>
              </Tooltip>
            ) : null}
            {canDeleteRoles ? (
              <Tooltip title={isDeletable ? 'Delete' : (deleteGuardReason ?? 'Role cannot be deleted')}>
                <span>
                  <IconButton size='small' color='error' disabled={!isDeletable} onClick={() => onDelete(row)}>
                    <i className='bx bx-trash' aria-hidden='true' />
                  </IconButton>
                </span>
              </Tooltip>
            ) : null}
          </Stack>
        )
      }
    }
  ]
}
