'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Grid from '@mui/material/Grid'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import Typography from '@mui/material/Typography'

import type { GridSortModel } from '@mui/x-data-grid'
import { Bounce, toast, ToastContainer } from 'react-toastify'

import CustomDialog from '@components/app/CustomDialog'
import CustomKPICard from '@components/app/CustomKPICard'
import CustomPageHeader from '@components/app/CustomPageHeader'
import CustomTable from '@components/app/CustomTable'
import UserManagementSkeleton from '@views/(protected)/users/user-management/components/UserManagementSkeleton'
import UserManagementStateCard from '@views/(protected)/users/user-management/components/UserManagementStateCard'

import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { CompanyStructureApiError } from '@/features/(protected)/users/company-structure/api/company-structure.api'
import { orgUnitFormSchema } from '@/features/(protected)/users/company-structure/model/company-structure.schema'
import {
  type CompanyStructureRow,
  type OrgUnitFormValues,
  type OrgUnitRecord,
  type OrgUnitStatus,
  type OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import CompanyStructureFilters from '@views/(protected)/users/company-structure/components/CompanyStructureFilters'
import OrgUnitDetailsPanel from '@views/(protected)/users/company-structure/components/OrgUnitDetailsPanel'
import OrgUnitFormDrawer from '@views/(protected)/users/company-structure/components/OrgUnitFormDrawer'
import OrgUnitHierarchyPanel from '@views/(protected)/users/company-structure/components/OrgUnitHierarchyPanel'
import { getOrgUnitTableColumns } from '@views/(protected)/users/company-structure/components/OrgUnitTableColumns'
import { useCompanyStructureController } from '@views/(protected)/users/company-structure/useCompanyStructureController'

type StatusDialogState = {
  orgUnit: CompanyStructureRow
  nextStatus: OrgUnitStatus
}

type DeleteDialogState = {
  orgUnit: CompanyStructureRow
}

function toErrorMessage(error: unknown, fallback: string) {
  if (error instanceof CompanyStructureApiError || error instanceof Error) {
    return error.message
  }

  return fallback
}

function getInitialValues(parent: CompanyStructureRow | null): Partial<OrgUnitFormValues> | null {
  if (!parent) {
    return null
  }

  if (parent.type === 'team') {
    return null
  }

  const nextType: OrgUnitType =
    parent.type === 'head_office' ? 'branch' : parent.type === 'branch' ? 'department' : 'team'

  return {
    parentId: parent.id,
    type: nextType,
    location: parent.location ?? ''
  }
}

function sortRows(rows: CompanyStructureRow[], sortModel: GridSortModel) {
  const primarySort = sortModel[0]

  if (!primarySort?.field || !primarySort.sort) {
    return rows
  }

  const direction = primarySort.sort === 'desc' ? -1 : 1

  return [...rows].sort((left, right) => {
    const leftValue = left[primarySort.field as keyof CompanyStructureRow]
    const rightValue = right[primarySort.field as keyof CompanyStructureRow]

    if (typeof leftValue === 'number' && typeof rightValue === 'number') {
      return (leftValue - rightValue) * direction
    }

    return String(leftValue ?? '').localeCompare(String(rightValue ?? '')) * direction
  })
}

function CompanyStructurePage() {
  const access = useCurrentTenantAccess()
  const canCreateOrgUnits = access.can(APP_ABILITIES.orgUnitsCreate)
  const canEditOrgUnits = access.can(APP_ABILITIES.orgUnitsWrite)
  const canDeleteOrgUnits = access.can(APP_ABILITIES.orgUnitsDelete)
  const canManageStructure = canCreateOrgUnits || canEditOrgUnits || canDeleteOrgUnits

  const {
    units,
    hierarchy,
    stats,
    error,
    isBootstrapping,
    isRefreshing,
    isSaving,
    createOrgUnit,
    updateOrgUnit,
    updateOrgUnitStatus,
    deleteOrgUnit
  } = useCompanyStructureController()

  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState<OrgUnitType | ''>('')
  const [statusFilter, setStatusFilter] = useState<OrgUnitStatus | ''>('')
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: 'asc' }])
  const [viewMode, setViewMode] = useState<'structure' | 'table'>('structure')

  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [drawerMode, setDrawerMode] = useState<'create' | 'edit'>('create')
  const [editingOrgUnit, setEditingOrgUnit] = useState<CompanyStructureRow | null>(null)
  const [initialValues, setInitialValues] = useState<Partial<OrgUnitFormValues> | null>(null)
  const [selectedOrgUnitId, setSelectedOrgUnitId] = useState<string | null>(null)
  const [statusDialog, setStatusDialog] = useState<StatusDialogState | null>(null)
  const [deleteDialog, setDeleteDialog] = useState<DeleteDialogState | null>(null)
  const [pageError, setPageError] = useState<string | null>(null)

  const debouncedSearch = useDebouncedValue(search.trim().toLowerCase(), 350)

  const directChildrenCountMap = useMemo(() => {
    const counts = new Map<string, number>()

    for (const unit of units) {
      if (!unit.parentId) {
        continue
      }

      counts.set(unit.parentId, (counts.get(unit.parentId) ?? 0) + 1)
    }

    return counts
  }, [units])

  const unitMap = useMemo(() => new Map(units.map(unit => [unit.id, unit])), [units])

  const rows = useMemo<CompanyStructureRow[]>(() => {
    return units.map(unit => ({
      ...unit,
      parentName: unit.parentId ? (unitMap.get(unit.parentId)?.name ?? null) : null,
      directChildrenCount: directChildrenCountMap.get(unit.id) ?? 0
    }))
  }, [directChildrenCountMap, unitMap, units])

  const filteredRows = useMemo(() => {
    const filtered = rows.filter(row => {
      const matchesSearch =
        debouncedSearch.length === 0 ||
        row.name.toLowerCase().includes(debouncedSearch) ||
        row.code.toLowerCase().includes(debouncedSearch) ||
        (row.location ?? '').toLowerCase().includes(debouncedSearch) ||
        (row.description ?? '').toLowerCase().includes(debouncedSearch)

      const matchesType = typeFilter === '' || row.type === typeFilter
      const matchesStatus = statusFilter === '' || row.status === statusFilter

      return matchesSearch && matchesType && matchesStatus
    })

    return sortRows(filtered, sortModel)
  }, [debouncedSearch, rows, sortModel, statusFilter, typeFilter])

  const parentOptions = useMemo(() => {
    return rows
      .filter(row => row.id !== editingOrgUnit?.id)
      .map(row => ({
        id: row.id,
        name: row.name,
        code: row.code,
        type: row.type
      }))
  }, [editingOrgUnit?.id, rows])

  const selectedOrgUnit = useMemo(() => {
    return rows.find(row => row.id === selectedOrgUnitId) ?? null
  }, [rows, selectedOrgUnitId])

  useEffect(() => {
    if (rows.length === 0) {
      setSelectedOrgUnitId(null)

      return
    }

    if (!selectedOrgUnitId || !rows.some(row => row.id === selectedOrgUnitId)) {
      setSelectedOrgUnitId(rows[0].id)
    }
  }, [rows, selectedOrgUnitId])

  const clearFilters = useCallback(() => {
    setSearch('')
    setTypeFilter('')
    setStatusFilter('')
  }, [])

  const closeDrawer = useCallback(() => {
    setIsDrawerOpen(false)
    setEditingOrgUnit(null)
    setInitialValues(null)
    setPageError(null)
    setDrawerMode('create')
  }, [])

  const openCreateRoot = useCallback(() => {
    if (!canCreateOrgUnits) {
      return
    }

    setDrawerMode('create')
    setEditingOrgUnit(null)
    setInitialValues(null)
    setIsDrawerOpen(true)
  }, [canCreateOrgUnits])

  const openCreateChild = useCallback(
    (orgUnit: OrgUnitRecord) => {
      if (!canCreateOrgUnits) {
        return
      }

      const row = rows.find(item => item.id === orgUnit.id) ?? null

      setDrawerMode('create')
      setEditingOrgUnit(null)
      setInitialValues(getInitialValues(row))
      setIsDrawerOpen(true)
    },
    [canCreateOrgUnits, rows]
  )

  const openEdit = useCallback(
    (orgUnit: OrgUnitRecord) => {
      if (!canEditOrgUnits) {
        return
      }

      const row = rows.find(item => item.id === orgUnit.id) ?? null

      setEditingOrgUnit(row)
      setInitialValues(null)
      setDrawerMode('edit')
      setIsDrawerOpen(true)
    },
    [canEditOrgUnits, rows]
  )

  const openStatusDialog = useCallback(
    (orgUnit: OrgUnitRecord) => {
      if (!canEditOrgUnits) {
        return
      }

      const row = rows.find(item => item.id === orgUnit.id)

      if (!row) {
        return
      }

      setStatusDialog({
        orgUnit: row,
        nextStatus: row.status === 'active' ? 'inactive' : 'active'
      })
    },
    [canEditOrgUnits, rows]
  )

  const closeStatusDialog = useCallback(() => {
    setStatusDialog(null)
  }, [])

  const openDeleteDialog = useCallback(
    (orgUnit: OrgUnitRecord) => {
      if (!canDeleteOrgUnits) {
        return
      }

      const row = rows.find(item => item.id === orgUnit.id)

      if (!row) {
        return
      }

      setDeleteDialog({ orgUnit: row })
    },
    [canDeleteOrgUnits, rows]
  )

  const closeDeleteDialog = useCallback(() => {
    setDeleteDialog(null)
  }, [])

  const handleSubmit = useCallback(
    async (values: OrgUnitFormValues) => {
      const payload = orgUnitFormSchema.parse(values)
      const isEditing = !!editingOrgUnit

      setPageError(null)

      try {
        if (isEditing && !canEditOrgUnits) {
          throw new Error('You do not have permission to update company structure records.')
        }

        if (!isEditing && !canCreateOrgUnits) {
          throw new Error('You do not have permission to create company structure records.')
        }

        const response = await toast.promise(
          isEditing ? updateOrgUnit(editingOrgUnit.id, payload) : createOrgUnit(payload),
          {
            pending: isEditing ? 'Updating org unit…' : 'Creating org unit…',
            success: isEditing ? 'Org unit updated' : 'Org unit created',
            error: {
              render({ data }: { data: unknown }) {
                return toErrorMessage(data, isEditing ? 'Could not update org unit.' : 'Could not create org unit.')
              }
            }
          }
        )

        setSelectedOrgUnitId(response.data.id)
        closeDrawer()
      } catch (error) {
        setPageError(toErrorMessage(error, isEditing ? 'Could not update org unit.' : 'Could not create org unit.'))
      }
    },
    [canCreateOrgUnits, canEditOrgUnits, closeDrawer, createOrgUnit, editingOrgUnit, updateOrgUnit]
  )

  const handleConfirmStatus = useCallback(async () => {
    if (!statusDialog) {
      return
    }

    const { orgUnit, nextStatus } = statusDialog

    try {
      if (!canEditOrgUnits) {
        throw new Error('You do not have permission to update company structure status.')
      }

      const response = await toast.promise(updateOrgUnitStatus(orgUnit.id, { status: nextStatus }), {
        pending: nextStatus === 'active' ? 'Activating org unit…' : 'Deactivating org unit…',
        success: nextStatus === 'active' ? 'Org unit activated' : 'Org unit deactivated',
        error: {
          render({ data }: { data: unknown }) {
            return toErrorMessage(data, 'Could not update org unit status.')
          }
        }
      })

      setSelectedOrgUnitId(response.data.id)
      closeStatusDialog()
    } catch (error) {
      setPageError(toErrorMessage(error, 'Could not update org unit status.'))
    }
  }, [canEditOrgUnits, closeStatusDialog, statusDialog, updateOrgUnitStatus])

  const handleConfirmDelete = useCallback(async () => {
    if (!deleteDialog) {
      return
    }

    try {
      if (!canDeleteOrgUnits) {
        throw new Error('You do not have permission to delete company structure records.')
      }

      await toast.promise(deleteOrgUnit(deleteDialog.orgUnit.id), {
        pending: 'Deleting org unit…',
        success: 'Org unit deleted',
        error: {
          render({ data }: { data: unknown }) {
            return toErrorMessage(data, 'Could not delete org unit.')
          }
        }
      })

      if (selectedOrgUnitId === deleteDialog.orgUnit.id) {
        setSelectedOrgUnitId(null)
      }

      closeDeleteDialog()
    } catch (error) {
      setPageError(toErrorMessage(error, 'Could not delete org unit.'))
    }
  }, [canDeleteOrgUnits, closeDeleteDialog, deleteDialog, deleteOrgUnit, selectedOrgUnitId])

  const columns = useMemo(
    () =>
      getOrgUnitTableColumns({
        canEdit: canEditOrgUnits,
        canDelete: canDeleteOrgUnits,
        onInspect: row => {
          setSelectedOrgUnitId(row.id)
          setViewMode('structure')
        },
        onEdit: row => openEdit(row),
        onToggleStatus: row => openStatusDialog(row),
        onDelete: row => openDeleteDialog(row)
      }),
    [canDeleteOrgUnits, canEditOrgUnits, openDeleteDialog, openEdit, openStatusDialog]
  )

  if (isBootstrapping) {
    return <UserManagementSkeleton />
  }

  if (rows.length === 0 && !isRefreshing) {
    return (
      <UserManagementStateCard
        title='Company structure is empty'
        description='Add your first head office, branch, department, or team so users and future org-based access can be organized clearly.'
        iconClassName='bx bx-sitemap'
        actionLabel={canCreateOrgUnits ? 'Create First Org Unit' : undefined}
        onAction={canCreateOrgUnits ? openCreateRoot : undefined}
      />
    )
  }

  return (
    <>
      <ToastContainer
        autoClose={2500}
        hideProgressBar={false}
        closeOnClick={true}
        pauseOnHover
        theme='light'
        transition={Bounce}
        position='top-center'
        pauseOnFocusLoss={false}
      />

      <Stack spacing={5}>
        <CustomPageHeader
          title='Company Structure'
          description='Manage branches, departments, teams, and head office units in a clear operating hierarchy.'
        >
          {canCreateOrgUnits ? (
            <Button variant='contained' startIcon={<i className='bx bx-plus' />} onClick={openCreateRoot}>
              Create Org Unit
            </Button>
          ) : null}
        </CustomPageHeader>

        {pageError || error ? (
          <Alert severity='error'>
            {pageError ?? (error instanceof Error ? error.message : 'Could not load company structure.')}
          </Alert>
        ) : null}

        {!canManageStructure ? (
          <Alert severity='info'>
            You have view-only access to company structure. Create, edit, and delete controls appear only when your role
            grants those specific permissions.
          </Alert>
        ) : null}

        <Grid container spacing={4}>
          <Grid size={{ xs: 12, md: 3 }}>
            <CustomKPICard label='Total Org Units' value={stats.total} iconString='bx bx-sitemap' iconColor='primary' />
          </Grid>
          <Grid size={{ xs: 12, md: 3 }}>
            <CustomKPICard
              label='Active Units'
              value={stats.active}
              iconString='bx bx-check-circle'
              iconColor='success'
            />
          </Grid>
          <Grid size={{ xs: 12, md: 3 }}>
            <CustomKPICard
              label='Operating Sites'
              value={stats.headOffice + stats.branches}
              iconString='bx bx-buildings'
              iconColor='info'
            />
          </Grid>
          <Grid size={{ xs: 12, md: 3 }}>
            <CustomKPICard
              label='Inactive Units'
              value={stats.inactive}
              iconString='bx bx-lock-alt'
              iconColor='error'
            />
          </Grid>
        </Grid>

        <Paper sx={{ overflow: 'hidden' }}>
          <Tabs
            value={viewMode}
            onChange={(_, value: 'structure' | 'table') => setViewMode(value)}
            sx={{ pt: 2, px: 4 }}
          >
            <Tab icon={<i className='bx bx-layer' />} iconPosition='start' label='Structure View' value='structure' />

            <Tab icon={<i className='bx bx-data' />} iconPosition='start' label='Table View' value='table' />
          </Tabs>

          {viewMode === 'structure' ? (
            <Stack spacing={2}>
              <Grid container spacing={4}>
                <Grid size={{ xs: 12, lg: 8 }}>
                  <OrgUnitHierarchyPanel
                    hierarchy={hierarchy}
                    selectedOrgUnitId={selectedOrgUnitId}
                    canManage={canManageStructure}
                    onSelect={orgUnit => setSelectedOrgUnitId(orgUnit.id)}
                  />
                </Grid>

                <Grid size={{ xs: 12, lg: 4 }}>
                  <OrgUnitDetailsPanel
                    orgUnit={selectedOrgUnit}
                    canCreate={canCreateOrgUnits}
                    canEdit={canEditOrgUnits}
                    canDelete={canDeleteOrgUnits}
                    onCreateRoot={openCreateRoot}
                    onCreateChild={openCreateChild}
                    onEdit={openEdit}
                    onToggleStatus={openStatusDialog}
                    onDelete={openDeleteDialog}
                  />
                </Grid>
              </Grid>
            </Stack>
          ) : (
            <>
              <CompanyStructureFilters
                searchValue={search}
                typeValue={typeFilter}
                statusValue={statusFilter}
                onSearchChange={setSearch}
                onTypeChange={setTypeFilter}
                onStatusChange={setStatusFilter}
                onReset={clearFilters}
              />

              <CustomTable
                rows={filteredRows}
                columns={columns}
                loading={isRefreshing}
                getRowId={row => row.id}
                disableRowSelectionOnClick
                sortModel={sortModel}
                onSortModelChange={setSortModel}
              />
            </>
          )}
        </Paper>
      </Stack>

      <OrgUnitFormDrawer
        open={isDrawerOpen}
        mode={drawerMode}
        defaultOrgUnit={editingOrgUnit}
        initialValues={initialValues}
        parentOptions={parentOptions}
        isSubmitting={isSaving}
        onClose={closeDrawer}
        onSubmit={handleSubmit}
      />

      <CustomDialog
        open={!!statusDialog}
        onClose={closeStatusDialog}
        closeAfterTransition
        title={statusDialog?.nextStatus === 'active' ? 'Activate Org Unit' : 'Deactivate Org Unit'}
        description={
          statusDialog ? (
            <Stack spacing={1}>
              <Typography variant='body2' color='text.secondary'>
                {statusDialog.nextStatus === 'active'
                  ? `This will reactivate ${statusDialog.orgUnit.name} so it can be used in the company structure again.`
                  : `This will deactivate ${statusDialog.orgUnit.name} and keep it out of active operational assignment.`}
              </Typography>
              {statusDialog.nextStatus === 'inactive' && statusDialog.orgUnit.directChildrenCount > 0 ? (
                <Typography variant='caption' color='text.secondary'>
                  Direct and nested child units will also be set to inactive to keep the hierarchy consistent.
                </Typography>
              ) : null}
            </Stack>
          ) : (
            ''
          )
        }
        actions={
          <>
            <Button variant='text' color='secondary' onClick={closeStatusDialog}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color={statusDialog?.nextStatus === 'active' ? 'success' : 'error'}
              onClick={() => void handleConfirmStatus()}
            >
              {statusDialog?.nextStatus === 'active' ? 'Activate' : 'Deactivate'}
            </Button>
          </>
        }
      >
        <Box />
      </CustomDialog>

      <CustomDialog
        open={!!deleteDialog}
        onClose={closeDeleteDialog}
        closeAfterTransition
        title='Delete Org Unit'
        description={
          deleteDialog ? (
            <Stack spacing={1}>
              <Typography variant='body2' color='text.secondary'>
                Delete {deleteDialog.orgUnit.name}? This action removes the org unit from the company structure.
              </Typography>
              <Typography variant='caption' color='text.secondary'>
                Only leaf org units with no assigned members can be deleted.
              </Typography>
            </Stack>
          ) : (
            ''
          )
        }
        actions={
          <>
            <Button variant='text' color='secondary' onClick={closeDeleteDialog}>
              Cancel
            </Button>
            <Button variant='contained' color='error' onClick={() => void handleConfirmDelete()}>
              Delete
            </Button>
          </>
        }
      >
        {null}
      </CustomDialog>
    </>
  )
}

export default CompanyStructurePage
