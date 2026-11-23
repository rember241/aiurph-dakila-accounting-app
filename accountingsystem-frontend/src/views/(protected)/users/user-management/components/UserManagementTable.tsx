import type { GridColDef, GridRowSelectionModel, GridSortModel } from '@mui/x-data-grid'

import CustomTable from '@components/app/CustomTable'

import type { UserRecord } from '@/features/(protected)/users/user-management/model/user-management.types'

type UserManagementTableProps = {
  rows: UserRecord[]
  columns: GridColDef<UserRecord>[]
  loading?: boolean
  sortModel: GridSortModel
  onSortModelChange: (model: GridSortModel) => void
  isRowSelectable?: (params: { row: UserRecord }) => boolean
  enableRowSelection?: boolean
  rowSelectionModel: GridRowSelectionModel
  onRowSelectionModelChange: (model: GridRowSelectionModel) => void
}

function UserManagementTable({
  rows,
  columns,
  loading,
  sortModel,
  onSortModelChange,
  isRowSelectable,
  enableRowSelection = true,
  rowSelectionModel,
  onRowSelectionModelChange
}: UserManagementTableProps) {
  return (
    <CustomTable<UserRecord>
      rows={rows}
      columns={columns}
      loading={loading}
      getRowId={row => row.id}
      checkBoxSelection={enableRowSelection}
      sortingMode='client'
      sortModel={sortModel}
      onSortModelChange={onSortModelChange}
      isRowSelectable={isRowSelectable}
      rowSelectionModel={rowSelectionModel}
      onRowSelectionModelChange={onRowSelectionModelChange}
      disableRowSelectionOnClick={true}
      sx={{
        '& .users-last-active-cell': {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        },
        '& .users-actions-cell': {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        }
      }}
    />
  )
}

export default UserManagementTable
