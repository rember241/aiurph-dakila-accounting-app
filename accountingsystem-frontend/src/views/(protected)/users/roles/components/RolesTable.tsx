import type { GridColDef, GridRowSelectionModel, GridSortModel } from '@mui/x-data-grid'

import CustomTable from '@components/app/CustomTable'

import type { RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'

type RolesTableProps = {
  rows: RoleRecord[]
  columns: GridColDef<RoleRecord>[]
  loading?: boolean
  sortModel: GridSortModel
  onSortModelChange: (model: GridSortModel) => void
  enableRowSelection?: boolean
  isRowSelectable?: (params: { row: RoleRecord }) => boolean
  rowSelectionModel: GridRowSelectionModel
  onRowSelectionModelChange: (model: GridRowSelectionModel) => void
}

function RolesTable({
  rows,
  columns,
  loading,
  sortModel,
  onSortModelChange,
  enableRowSelection = true,
  isRowSelectable,
  rowSelectionModel,
  onRowSelectionModelChange
}: RolesTableProps) {
  return (
    <CustomTable<RoleRecord>
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
        '& .roles-members-cell': {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        },
        '& .roles-last-updated-cell': {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        },
        '& .roles-actions-cell': {
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center'
        }
      }}
    />
  )
}

export default RolesTable
