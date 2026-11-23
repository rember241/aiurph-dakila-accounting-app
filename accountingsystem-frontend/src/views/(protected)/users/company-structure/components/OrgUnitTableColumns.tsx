import Chip from '@mui/material/Chip'
import IconButton from '@mui/material/IconButton'
import Stack from '@mui/material/Stack'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'

import type { GridColDef } from '@mui/x-data-grid'

import {
  canDeleteOrgUnit,
  getOrgUnitDeleteGuardReason,
  getOrgUnitStatusLabel,
  getOrgUnitTypeLabel
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type { CompanyStructureRow } from '@/features/(protected)/users/company-structure/model/company-structure.types'

type OrgUnitTableColumnsOptions = {
  canEdit: boolean
  canDelete: boolean
  onInspect: (row: CompanyStructureRow) => void
  onEdit: (row: CompanyStructureRow) => void
  onToggleStatus: (row: CompanyStructureRow) => void
  onDelete: (row: CompanyStructureRow) => void
}

export function getOrgUnitTableColumns({
  canEdit,
  canDelete,
  onInspect,
  onEdit,
  onToggleStatus,
  onDelete
}: OrgUnitTableColumnsOptions): GridColDef<CompanyStructureRow>[] {
  return [
    {
      field: 'name',
      headerName: 'Org Unit',
      flex: 1.4,
      minWidth: 220,
      align: 'left',
      headerAlign: 'left',
      renderCell: ({ row }) => (
        <Stack
          spacing={0.25}
          sx={{ width: '100%', height: '100%', justifyContent: 'center', alignItems: 'flex-start', minWidth: 0 }}
        >
          <Typography variant='body2' fontWeight={600}>
            {row.name}
          </Typography>
          <Typography variant='caption' color='text.secondary'>
            {row.code}
          </Typography>
        </Stack>
      )
    },
    {
      field: 'type',
      headerName: 'Type',
      minWidth: 160,
      renderCell: ({ row }) => (
        <Chip size='small' color='primary' variant='tonal' label={getOrgUnitTypeLabel(row.type)} />
      )
    },
    {
      field: 'parentName',
      headerName: 'Reports To',
      flex: 1,
      minWidth: 180,
      valueGetter: (_, row) => row.parentName ?? 'Top level'
    },
    {
      field: 'directChildrenCount',
      headerName: 'Sub-units',
      minWidth: 120,
      type: 'number'
    },
    {
      field: 'membersCount',
      headerName: 'Members',
      minWidth: 120,
      type: 'number'
    },
    {
      field: 'status',
      headerName: 'Status',
      minWidth: 130,
      renderCell: ({ row }) => (
        <Chip
          size='small'
          color={row.status === 'active' ? 'success' : 'default'}
          variant='tonal'
          label={getOrgUnitStatusLabel(row.status)}
        />
      )
    },
    {
      field: 'location',
      headerName: 'Location',
      flex: 1,
      minWidth: 180,
      valueGetter: (_, row) => row.location ?? 'Not set'
    },
    {
      field: 'actions',
      headerName: 'Actions',
      sortable: false,
      filterable: false,
      minWidth: 140,
      align: 'right',
      headerAlign: 'right',
      renderCell: ({ row }) => {
        const deleteGuardReason = getOrgUnitDeleteGuardReason(row)
        const isDeletable = canDelete && canDeleteOrgUnit(row)

        return (
          <Stack direction='row' spacing={0.5}>
            <Tooltip title='Inspect'>
              <span>
                <IconButton size='small' onClick={() => onInspect(row)}>
                  <i className='bx bx-show-alt' />
                </IconButton>
              </span>
            </Tooltip>

            {canEdit || canDelete ? (
              <>
                {canEdit ? (
                  <>
                    <Tooltip title='Edit'>
                      <span>
                        <IconButton size='small' onClick={() => onEdit(row)}>
                          <i className='bx bx-pencil' />
                        </IconButton>
                      </span>
                    </Tooltip>

                    <Tooltip title={row.status === 'active' ? 'Deactivate' : 'Activate'}>
                      <span>
                        <IconButton size='small' onClick={() => onToggleStatus(row)}>
                          <i className={`bx ${row.status === 'active' ? 'bx-lock-alt' : 'bx-lock-open-alt'}`} />
                        </IconButton>
                      </span>
                    </Tooltip>
                  </>
                ) : null}

                {canDelete ? (
                  <Tooltip title={isDeletable ? 'Delete' : (deleteGuardReason ?? 'Org unit cannot be deleted')}>
                    <span>
                      <IconButton size='small' color='error' disabled={!isDeletable} onClick={() => onDelete(row)}>
                        <i className='bx bx-trash' />
                      </IconButton>
                    </span>
                  </Tooltip>
                ) : null}
              </>
            ) : null}
          </Stack>
        )
      }
    }
  ]
}
