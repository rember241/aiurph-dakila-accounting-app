import type { GridColDef } from '@mui/x-data-grid'
import { GridActionsCellItem } from '@mui/x-data-grid'

import { Box, Chip } from '@mui/material'

import type { JournalEntryColumn } from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

function getJournalEntryColumns(actions: {
  onView: (row: JournalEntryColumn) => void
  onEdit: (row: JournalEntryColumn) => void
  onDelete: (row: JournalEntryColumn) => void
  canManageEntries: boolean
}): GridColDef<JournalEntryColumn>[] {
  return [
    {
      field: 'date',
      headerName: 'DATE',
      type: 'string',
      flex: 1,
      sortable: true,
      filterable: false
    },
    {
      field: 'journalNumber',
      headerName: 'JOURNAL #',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: false,
      renderCell: params => {
        const row = params.row as JournalEntryColumn

        return `${row.prefix}-${String(row.journalNumber).padStart(4, '0')}`
      }
    },
    {
      field: 'referenceNumber',
      headerName: 'REFERENCE #',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: false,
      valueFormatter: (value: string) => {
        return value ? value : '-'
      }
    },
    {
      field: 'type',
      headerName: 'TYPE',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: true,
      renderCell: params => {
        const row = params.row as JournalEntryColumn
        const type = row.type.toUpperCase()

        switch (type) {
          case 'GENERAL':
            return <Chip color='primary' label={type} variant={'tonal'} />
          case 'ADJUSTING':
            return <Chip color='warning' label={type} variant={'tonal'} />
          case 'CLOSING':
            return <Chip color='success' label={type} variant={'tonal'} />
        }
      }
    },
    {
      field: 'status',
      headerName: 'STATUS',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: true,
      renderCell: params => {
        const row = params.row as JournalEntryColumn
        const status = row.status.toUpperCase()

        switch (status) {
          case 'POSTED':
            return <Chip color='info' label={status} variant={'tonal'} />

          case 'DRAFT':
            return <Chip color='secondary' label={status} variant={'tonal'} />

          case 'VOID':
            return <Chip color='error' label={status} variant={'tonal'} />
        }
      }
    },
    {
      field: 'fundSource',
      headerName: 'FUND SOURCE',
      type: 'string',
      flex: 1,
      sortable: false,
      filterable: false
    },
    {
      field: 'note',
      headerName: 'NOTE',
      type: 'string',
      flex: 2,
      sortable: false,
      filterable: false,
      valueFormatter: (value: string) => {
        return value ? value : '-'
      }
    },
    {
      field: 'amount',
      headerName: 'AMOUNT',
      type: 'number',
      flex: 1,
      sortable: false,
      filterable: false,
      valueFormatter: (value: number) => {
        return currencyFormatter.format(value)
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
        const row = params.row as JournalEntryColumn

        return [
          <Box key={`view`} sx={{ pr: 0 }}>
            <GridActionsCellItem label='View' icon={<i className='bx bx-show' />} onClick={() => actions.onView(row)} />
          </Box>,
          <Box key={`edit`}>
            <GridActionsCellItem
              label='Edit'
              disabled={!actions.canManageEntries || row.status !== 'draft'}
              icon={<i className='bx bx-pencil' />}
              onClick={() => actions.onEdit(row)}
            />
          </Box>,
          <Box key={`delete`} sx={{ pr: 0 }}>
            <GridActionsCellItem
              label='Delete'
              disabled={!actions.canManageEntries || row.status === 'void'}
              icon={<i className='bx bx-trash' />}
              onClick={() => actions.onDelete(row)}
            />
          </Box>
        ]
      }
    }
  ]
}

export default getJournalEntryColumns
