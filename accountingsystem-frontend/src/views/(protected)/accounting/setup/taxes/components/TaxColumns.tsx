import type { GridColDef } from '@mui/x-data-grid'
import { GridActionsCellItem } from '@mui/x-data-grid'

import { Box, Chip } from '@mui/material'

import type { TaxColumn } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

function getTaxColumns(actions: {
  onEdit: (row: TaxColumn) => void
  onDelete: (row: TaxColumn) => void
}): GridColDef<TaxColumn>[] {
  return [
    {
      field: 'name',
      headerName: 'NAME',
      type: 'string',
      flex: 2,
      sortable: true,
      filterable: false
    },
    {
      field: 'rate',
      headerName: 'RATE',
      type: 'number',
      flex: 1,
      sortable: true,
      filterable: false,
      valueFormatter: (value: number) => {
        return `${value}%`
      }
    },
    {
      field: 'status',
      headerName: 'STATUS',
      type: 'string',
      flex: 1,
      headerAlign: 'center',
      align: 'center',
      sortable: false,
      filterable: true,
      renderCell: params => {
        const row = params.row as TaxColumn
        const status = row.status.toUpperCase()

        return status === 'ACTIVE' ? (
          <Chip color='success' label={status} variant={'tonal'} />
        ) : (
          <Chip color='error' label={status} variant={'tonal'} />
        )
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
        const row = params.row as TaxColumn

        return [
          <Box key={`edit(${row.id})`} sx={{ pr: 0 }}>
            <GridActionsCellItem
              icon={<i className='bx bx-pencil' />}
              label='Edit'
              onClick={() => actions.onEdit(row)}
            />
          </Box>,
          <Box key={`delete(${row.id})`} sx={{ pr: 0 }}>
            <GridActionsCellItem
              icon={<i className='bx bx-trash' />}
              label='Delete'
              onClick={() => actions.onDelete(row)}
            />
          </Box>
        ]
      }
    }
  ]
}

export default getTaxColumns
