import type { GridColDef } from '@mui/x-data-grid'
import { GridActionsCellItem } from '@mui/x-data-grid'

import { Box, Chip } from '@mui/material'

import type { JournalNumberColumn } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

/**
 * Column factory for the transaction-series register.
 */
function getTransactionSeriesColumns(actions: {
  onEdit: (row: JournalNumberColumn) => void
  onDelete: (row: JournalNumberColumn) => void
}): GridColDef<JournalNumberColumn>[] {
  return [
    {
      field: 'name',
      headerName: 'TRANSACTION NAME',
      type: 'string',
      flex: 2,
      sortable: true,
      filterable: false
    },
    {
      field: 'prefix',
      headerName: 'PREFIX',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: false,
      valueFormatter: (value: string) => {
        return `${value}-`
      }
    },
    {
      field: 'startingNumber',
      headerName: 'STARTING NUMBER',
      type: 'number',
      flex: 1,
      align: 'left',
      headerAlign: 'left',
      sortable: false,
      filterable: false,
      valueFormatter: (value: number) => {
        const parsedNumber = String(value)

        // Add leading zeroes when presenting in the table
        return parsedNumber.padStart(4, '0')
      }
    },
    {
      field: 'preview',
      headerName: 'PREVIEW',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: false,
      renderCell: params => {
        const row = params.row as JournalNumberColumn

        return `${row.prefix}-${String(row.startingNumber).padStart(4, '0')}`
      }
    },
    {
      field: 'status',
      headerName: 'STATUS',
      type: 'string',
      flex: 1,
      align: 'center',
      headerAlign: 'center',
      sortable: false,
      filterable: true,
      renderCell: params => {
        const row = params.row as JournalNumberColumn
        const status = row.status.toUpperCase()

        return status === 'ACTIVE' ? (
          <Chip color='success' label={status} variant={'tonal'} />
        ) : (
          <Chip color='error' label={status} variant={'tonal'} />
        )
      }
    },
    {
      field: 'resetInterval',
      headerName: 'RESET INTERVAL',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: true,
      valueFormatter: (value: string) => {
        // Capitalize first letter of the word
        return `${value.charAt(0).toUpperCase()}${value.slice(1)}`
      }
    },
    {
      field: 'actions',
      headerName: 'ACTIONS',
      type: 'actions',
      width: 150,
      sortable: false,
      filterable: false,
      renderCell: params => {
        const row = params.row as JournalNumberColumn

        return [
          <Box key={`edit`}>
            <GridActionsCellItem
              label='Edit'
              icon={<i className='bx bx-pencil' />}
              onClick={() => actions.onEdit(row)}
            />
          </Box>,
          <Box key={`delete`} sx={{ pr: 0 }}>
            <GridActionsCellItem
              label='Delete'
              icon={<i className='bx bx-trash' />}
              onClick={() => actions.onDelete(row)}
            />
          </Box>
        ]
      }
    }
  ]
}

export default getTransactionSeriesColumns
