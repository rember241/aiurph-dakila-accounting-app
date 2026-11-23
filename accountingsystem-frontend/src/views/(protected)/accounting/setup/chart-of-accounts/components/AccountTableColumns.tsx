import { GridActionsCellItem, type GridColDef } from '@mui/x-data-grid'

import Box from '@mui/material/Box'

import Chip from '@mui/material/Chip'

import Tooltip from '@mui/material/Tooltip'

import type { AccountColumn } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

export function getAccountTableColumns(actions: {
  onView: (row: AccountColumn) => void
  onEdit: (row: AccountColumn) => void
  onDelete: (row: AccountColumn) => void
  canEditAccounts?: boolean
  canDeleteAccounts?: boolean
}): GridColDef<AccountColumn>[] {
  const canEditAccounts = actions.canEditAccounts ?? true
  const canDeleteAccounts = actions.canDeleteAccounts ?? true

  return [
    {
      field: '__check__',
      headerName: '',
      align: 'center',
      sortable: false,
      filterable: false
    },
    {
      field: 'name',
      headerName: 'NAME',
      flex: 1,
      renderCell: params => {
        const row = params.row

        const isSystem = row.is_system

        return (
          <Box display='inline-flex' alignItems='center' gap={2}>
            <Box display='flex' alignItems='center' gap={2}>
              <Tooltip
                color={'warning'}
                title={isSystem ? 'This account is a system account and cannot be modified' : ''}
              >
                <Chip
                  size='small'
                  color={isSystem ? 'secondary' : 'primary'}
                  icon={isSystem ? <i className='bx bx-lock' style={{ fontSize: 16 }} /> : <i className='bx bx-user' />}
                  label={isSystem ? 'System' : 'Custom'}
                  variant='tonal'
                />
              </Tooltip>
            </Box>
            {row.name}
          </Box>
        )
      }
    },
    {
      field: 'account_type_name',
      headerName: 'ACCOUNT TYPE',
      flex: 1,
      renderCell: params => {
        const row = params.row
        const isBalanceSheet = row.statement_group === 'balance_sheet'

        return (
          <Box display='inline-flex' alignItems='center' gap={2}>
            <Chip
              color={isBalanceSheet ? 'primary' : 'warning'}
              label={isBalanceSheet ? 'BAL' : 'INC'}
              variant='tonal'
            />
            {row.account_type_name}
          </Box>
        )
      }
    },
    {
      field: 'detail_type_name',
      headerName: 'DETAIL TYPE',
      flex: 1,
      renderCell: params => {
        const row = params.row

        return row.detail_type_name ?? '-'
      }
    },

    // {
    //   field: 'is_posting',
    //   headerName: 'POSTING',
    //   type: 'boolean',
    //   width: 120,
    //   renderCell: params => {
    //     const row = params.row as AccountColumn
    //
    //     return row.is_posting ? (
    //       <Chip color={'info'} label={'Posting'} variant={'tonal'} />
    //     ) : (
    //       <Chip color={'secondary'} label={'Non-Posting'} variant={'tonal'} />
    //     )
    //   }
    // },

    {
      field: 'actions',
      headerName: 'ACTIONS',
      type: 'actions',
      renderCell: params => {
        const row = params.row

        const locked = row.is_system

        return [
          <Box key='view' sx={{ pr: 0 }}>
            <GridActionsCellItem icon={<i className='bx bx-show' />} label='View' onClick={() => actions.onView(row)} />
          </Box>,
          <Box key='edit' sx={{ pr: 0 }}>
            <GridActionsCellItem
              icon={<i className='bx bx-pencil' />}
              label='Edit'
              onClick={() => actions.onEdit(row)}
              disabled={locked || !canEditAccounts}
            />
          </Box>,
          ...(canDeleteAccounts
            ? [
                <Box key='delete' sx={{ pr: 0 }}>
                  <GridActionsCellItem
                    icon={<i className='bx bx-trash' />}
                    label='Delete'
                    onClick={() => actions.onDelete(row)}
                    disabled={locked}
                  />
                </Box>
              ]
            : [])
        ]
      }
    }
  ]
}

export default getAccountTableColumns
