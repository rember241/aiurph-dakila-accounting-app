import type { GridColDef } from '@mui/x-data-grid'
import { GridActionsCellItem } from '@mui/x-data-grid'

import { Box, Chip, Typography } from '@mui/material'

import type { GeneralLedgerAccount } from '@/features/(protected)/accounting/books/general-ledger/model/general-ledger.types'

import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

function getAccountColumns(actions: {
  onView: (row: GeneralLedgerAccount) => void
}): GridColDef<GeneralLedgerAccount>[] {
  return [
    {
      field: 'code',
      headerName: 'ACCOUNT CODE',
      type: 'string',
      flex: 1,
      sortable: true,
      filterable: false
    },
    {
      field: 'name',
      headerName: 'ACCOUNT NAME',
      type: 'string',
      flex: 2,
      sortable: true,
      filterable: false
    },
    {
      field: 'type',
      headerName: 'ACCOUNT TYPE',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: true,
      renderCell: params => {
        const type = String(params.value ?? '')

        const color =
          type === 'asset'
            ? 'primary'
            : type === 'liability'
              ? 'warning'
              : type === 'equity'
                ? 'info'
                : type === 'revenue'
                  ? 'success'
                  : 'error'

        return (
          <Chip
            size='small'
            variant='tonal'
            color={color}
            label={type || 'Unknown'}
            sx={{ textTransform: 'capitalize' }}
          />
        )
      }
    },
    {
      field: 'openingBalance',
      headerName: 'OPENING BALANCE',
      type: 'number',
      flex: 1,
      sortable: true,
      filterable: false,
      valueFormatter: (value: number) => {
        return currencyFormatter.format(value)
      }
    },
    {
      field: 'debit',
      headerName: 'DEBIT',
      type: 'number',
      flex: 1,
      sortable: true,
      filterable: false,
      valueFormatter: (value: number) => {
        return currencyFormatter.format(value)
      }
    },
    {
      field: 'credit',
      headerName: 'CREDIT',
      type: 'number',
      flex: 1,
      sortable: true,
      filterable: false,
      valueFormatter: (value: number) => {
        return currencyFormatter.format(value)
      }
    },
    {
      field: 'balance',
      headerName: 'ENDING BALANCE',
      headerAlign: 'center',
      align: 'center',
      type: 'number',
      flex: 1,
      sortable: true,
      filterable: false,
      renderCell: params => {
        const row = params.row as GeneralLedgerAccount
        const textColor = row.balance > 0 ? 'success.main' : row.balance < 0 ? 'error.main' : 'text.secondary'

        return (
          <Typography
            variant='body2'
            fontWeight={700}
            color={textColor}
            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%' }}
          >
            {currencyFormatter.format(row.balance)}
          </Typography>
        )
      }
    },
    {
      field: 'actions',
      headerName: 'ACTIONS',
      type: 'actions',
      flex: 1,
      sortable: false,
      filterable: false,
      renderCell: params => {
        const row = params.row as GeneralLedgerAccount

        return (
          <Box key={`view`} sx={{ pr: 0 }}>
            <GridActionsCellItem label='View' icon={<i className='bx bx-show' />} onClick={() => actions.onView(row)} />
          </Box>
        )
      }
    }
  ]
}

export default getAccountColumns
