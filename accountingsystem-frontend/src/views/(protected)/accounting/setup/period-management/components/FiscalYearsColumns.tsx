import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'

import Tooltip from '@mui/material/Tooltip'

import { GridActionsCellItem, type GridColDef } from '@mui/x-data-grid'

import type { FiscalYear } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

export function getFiscalYearsTableColumns(actions: {
  onEdit: (row: FiscalYear) => void
  onDelete: (row: FiscalYear) => void
  canEditFiscalYears?: boolean
  canDeleteFiscalYears?: boolean
}): GridColDef<FiscalYear>[] {
  const columns: GridColDef<FiscalYear>[] = [
    {
      field: '__check__',
      headerName: '',
      align: 'center',
      sortable: false,
      filterable: false
    },
    {
      field: 'code',
      headerName: 'CODE',
      width: 100,
      sortable: false,
      filterable: false
    },
    {
      field: 'status',
      headerName: 'STATUS',
      sortable: false,
      filterable: false,
      renderCell: params => {
        const { status } = params.row as FiscalYear

        return (
          <Chip
            label={status.toUpperCase()}
            color={status === 'open' ? 'success' : 'error'}
            size='small'
            variant='tonal'
          />
        )
      }
    },
    {
      field: 'name',
      headerName: 'NAME',
      width: 180,
      flex: 1,

      renderCell: params => {
        const { is_current, name } = params.row as FiscalYear

        return (
          <Box display='inline-flex' alignItems='center' gap={2}>
            {name}
            <Box display='flex' alignItems='center' gap={2}>
              <Tooltip
                color={'warning'}
                title={
                  is_current ? 'This period is the current period' : 'This period is locked and cannot be modified'
                }
              >
                <Chip
                  size='small'
                  color={is_current ? 'primary' : 'secondary'}
                  icon={is_current ? <i className='bx-check' style={{ fontSize: 18 }} /> : undefined}
                  label={is_current ? 'Current' : '-'}
                  variant='tonal'
                />
              </Tooltip>
            </Box>
          </Box>
        )
      }
    },
    {
      field: 'start_date',
      headerName: 'YEAR START DATE',
      width: 100,
      flex: 1,
      valueFormatter: value => {
        const date = new Date(value)
        const month = date.toLocaleString('en-US', { month: 'long' })
        const day = date.getDate()
        const year = date.getFullYear()

        return `${month} ${day}, ${year}`
      }
    },

    {
      field: 'end_date',
      headerName: 'YEAR END DATE',
      width: 100,
      flex: 1,
      valueFormatter: value => {
        const date = new Date(value)
        const month = date.toLocaleString('en-US', { month: 'long' })
        const day = date.getDate()
        const year = date.getFullYear()

        return `${month} ${day}, ${year}`
      }
    },

    {
      field: 'created_at',
      headerName: 'CREATED SINCE',
      width: 150,
      flex: 1,
      renderCell: params => {
        const date = new Date(params.value)
        const now = new Date()
        const diff = now.getTime() - date.getTime()
        const seconds = Math.floor(diff / 1000)
        const minutes = Math.floor(seconds / 60)
        const hours = Math.floor(minutes / 60)
        const days = Math.floor(hours / 24)

        if (days > 0) {
          return (
            <Box display='flex' alignItems='center' gap={1}>
              <i className='bx-time-five w-4 h-4' />
              {`${days} days ago`}{' '}
            </Box>
          )
        } else if (hours > 0) {
          return (
            <Box display='flex' alignItems='center' gap={1}>
              <i className='bx-time-five w-4 h-4' />
              {`${hours} hours ago`}{' '}
            </Box>
          )
        } else if (minutes > 0) {
          return (
            <Box display='flex' alignItems='center' gap={1}>
              <i className='bx-time-five w-4 h-4' />
              {`${minutes} minutes ago`}{' '}
            </Box>
          )
        } else {
          return (
            <Box display='flex' alignItems='center' gap={1}>
              <i className='bx-time-five w-4 h-4' />
              {`${seconds} seconds ago`}{' '}
            </Box>
          )
        }
      }
    }
  ]

  if (!(actions.canEditFiscalYears ?? true) && !(actions.canDeleteFiscalYears ?? true)) {
    return columns
  }

  return [
    ...columns,
    {
      field: 'actions',
      headerName: 'ACTIONS',
      type: 'actions',
      renderCell: params => {
        const row = params.row as FiscalYear

        return [
          ...((actions.canEditFiscalYears ?? true)
            ? [
                <Box key='settings' sx={{ pr: 0 }}>
                  <GridActionsCellItem
                    icon={<i className='bx-cog' />}
                    label='Settings'
                    onClick={() => actions.onEdit(row)}
                  />
                </Box>
              ]
            : []),
          ...((actions.canDeleteFiscalYears ?? true)
            ? [
                <Box key='delete' sx={{ pr: 0 }}>
                  <GridActionsCellItem
                    icon={<i className='bx bx-trash' />}
                    label='Delete'
                    onClick={() => actions.onDelete(row)}
                  />
                </Box>
              ]
            : [])
        ]
      }
    }
  ]
}

export default getFiscalYearsTableColumns
