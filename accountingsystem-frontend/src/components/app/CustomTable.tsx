'use client'

import React, { useEffect, useState } from 'react'

import { DataGrid } from '@mui/x-data-grid'

import type { GridColDef, GridRowIdGetter, GridValidRowModel, DataGridProps } from '@mui/x-data-grid'

import type { SxProps } from '@mui/system'

/**
 * Shared DataGrid wrapper used by most register-style ERP screens.
 *
 * Centralizing the defaults here keeps pagination, quick filtering, sizing,
 * and table spacing consistent across modules without every page rebuilding
 * the same DataGrid setup.
 */

type Props<T extends GridValidRowModel> = {
  rows: T[]
  columns: GridColDef<T>[]
  loading?: boolean
  getRowId?: GridRowIdGetter<T>

  // UI toggles
  checkBoxSelection?: boolean
  showToolbar?: boolean
  disableColumnResize?: boolean
  sx?: SxProps

  // server mode support (optional)
  paginationMode?: DataGridProps<T>['paginationMode']
  sortingMode?: DataGridProps<T>['sortingMode']
  filterMode?: DataGridProps<T>['filterMode']

  rowCount?: number
  paginationModel?: DataGridProps<T>['paginationModel']
  onPaginationModelChange?: DataGridProps<T>['onPaginationModelChange']
  isRowSelectable?: DataGridProps<T>['isRowSelectable']
  getRowClassName?: DataGridProps<T>['getRowClassName']
  getRowHeight?: DataGridProps<T>['getRowHeight']
  rowSelectionModel?: DataGridProps<T>['rowSelectionModel']
  onRowSelectionModelChange?: DataGridProps<T>['onRowSelectionModelChange']
  disableRowSelectionOnClick?: DataGridProps<T>['disableRowSelectionOnClick']

  sortModel?: DataGridProps<T>['sortModel']
  onSortModelChange?: DataGridProps<T>['onSortModelChange']

  // advanced overrides (optional)
  slots?: Partial<DataGridProps<T>['slots']>
  slotProps?: DataGridProps<T>['slotProps']
}

function CustomTable<T extends GridValidRowModel>({
  rows,
  columns,
  loading = false,
  getRowId,

  // Client Toggles
  checkBoxSelection = false,
  showToolbar = false,
  disableColumnResize = false,

  // server props
  paginationMode,
  sortingMode,
  filterMode,
  rowCount,
  paginationModel,
  onPaginationModelChange,
  sortModel,
  onSortModelChange,
  isRowSelectable,
  getRowClassName,
  rowSelectionModel,
  onRowSelectionModelChange,
  disableRowSelectionOnClick,

  // row props
  getRowHeight,

  // Advanced
  slots,
  slotProps,
  sx
}: Props<T>) {
  /**
   * MUI DataGrid has been noisy during hydration in local development for this
   * project. Waiting for mount keeps the shared wrapper stable for feature
   * pages until that upstream issue is cleaned up.
   */
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  if (!mounted) return null

  return (
    <DataGrid
      rows={rows}
      columns={columns}
      getRowId={getRowId}
      checkboxSelection={checkBoxSelection}
      loading={loading}
      showToolbar={showToolbar}
      paginationMode={paginationMode}
      sortingMode={sortingMode}
      filterMode={filterMode}
      rowCount={rowCount}
      paginationModel={paginationModel}
      onPaginationModelChange={onPaginationModelChange}
      sortModel={sortModel}
      onSortModelChange={onSortModelChange}
      isRowSelectable={isRowSelectable}
      getRowClassName={getRowClassName}
      rowSelectionModel={rowSelectionModel}
      onRowSelectionModelChange={onRowSelectionModelChange}
      disableRowSelectionOnClick={disableRowSelectionOnClick}
      initialState={{
        pagination: { paginationModel: { pageSize: 10 } }
      }}
      getRowHeight={getRowHeight}
      disableColumnResize={disableColumnResize}
      pageSizeOptions={[5, 10, 20, 30, 50]}
      slots={slots}
      slotProps={{
        toolbar: {
          // Give every table the same quick-filter behavior unless a page
          // explicitly overrides it.
          showQuickFilter: true,
          quickFilterProps: { debounceMs: 500 }
        },
        ...slotProps
      }}
      sx={{
        width: '100%',
        border: 0,
        backgroundColor: 'transparent',
        p: 5,
        '& .MuiDataGrid-toolbarQuickFilter': {
          marginRight: 'auto',
          order: -1
        },
        ...sx
      }}
    />
  )
}

export default CustomTable
