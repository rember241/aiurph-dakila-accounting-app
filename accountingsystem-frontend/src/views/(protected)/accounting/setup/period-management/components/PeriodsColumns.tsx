import React from 'react'

import Chip from '@mui/material/Chip'
import { Stack } from '@mui/material'

import Typography from '@mui/material/Typography'
import Box from '@mui/material/Box'

import { GridActionsCellItem, type GridColDef } from '@mui/x-data-grid'

import type { AccountingPeriod } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

type PeriodActions = {
  onLock: (row: AccountingPeriod) => void
  canManagePeriodStatus?: boolean
}

export function getPeriodsTableColumns(actions: PeriodActions): GridColDef<AccountingPeriod>[] {
  const columns: GridColDef<AccountingPeriod>[] = [
    {
      field: 'period_number',
      headerName: 'PERIOD',
      width: 140,

      renderCell: params => <strong>{params.value}</strong>
    },
    {
      field: 'name',
      headerName: 'PERIOD',
      width: 140,

      renderCell: params => <strong>{params.value}</strong>
    },

    {
      field: 'start_date',
      headerName: 'START DATE',
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
      headerName: 'END DATE',
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
      field: 'status',
      headerName: 'STATUS',
      sortable: false,
      filterable: false,
      width: 160,
      renderCell: params => {
        const { status } = params.row as AccountingPeriod

        return (
          <Box>
            <Chip
              icon={
                status === 'open' ? (
                  <i className='bx-lock-open-alt ' />
                ) : status === 'soft_closed' ? (
                  <i className='bx-lock-alt ' />
                ) : (
                  <i className='bx-lock-alt ' />
                )
              }
              label={status.replace(/_/g, ' ').toUpperCase()}
              color={status === 'open' ? 'success' : status === 'soft_closed' ? 'warning' : 'error'}
              size='small'
              variant='tonal'
            />
          </Box>
        )
      }
    }
  ]

  if (!(actions.canManagePeriodStatus ?? true)) {
    return columns
  }

  return [
    ...columns,
    {
      field: 'actions',
      headerName: 'ACTIONS',
      type: 'actions',
      renderHeader: params => (
        <Stack direction='row' alignItems='center' justifyContent={'center'} spacing={0.5}>
          <i className='bx-cog w-4 h-4' />
          <Typography variant='body1'>{params.colDef.headerName}</Typography>
        </Stack>
      ),
      renderCell: params => {
        const row = params.row as AccountingPeriod

        return (
          <Box key='settings' sx={{ pr: 0 }}>
            <GridActionsCellItem icon={<i className='bx-cog' />} label='Settings' onClick={() => actions.onLock(row)} />
          </Box>
        )
      }
    }
  ]
}

export default getPeriodsTableColumns
