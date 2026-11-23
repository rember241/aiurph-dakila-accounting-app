import type { GridColDef } from '@mui/x-data-grid'
import { GridActionsCellItem } from '@mui/x-data-grid'

import { Box, Chip } from '@mui/material'

import type { FundSourceColumn } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'

function getFundSourceColumns(actions: {
  onEdit: (row: FundSourceColumn) => void
  onDelete: (row: FundSourceColumn) => void
}): GridColDef<FundSourceColumn>[] {
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
      field: 'status',
      headerName: 'STATUS',
      type: 'string',
      flex: 1,
      headerAlign: 'center',
      align: 'center',
      sortable: false,
      filterable: true,
      renderCell: params => {
        const row = params.row as FundSourceColumn
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
        const row = params.row as FundSourceColumn

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

export default getFundSourceColumns
