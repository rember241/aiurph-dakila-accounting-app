'use client'

import React, { useCallback, useEffect, useMemo, useState } from 'react'

// Material UI Components
import Alert from '@mui/material/Alert'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Button from '@mui/material/Button'
import Tabs from '@mui/material/Tabs'

import Tab from '@mui/material/Tab'
import Typography from '@mui/material/Typography'

// MUI Data Grid
import type { GridPaginationModel } from '@mui/x-data-grid'

// React-toast
import { Bounce, toast, ToastContainer } from 'react-toastify'

// Custom Components
import CustomPageHeader from '@components/app/CustomPageHeader'
import CustomTable from '@components/app/CustomTable'
import CustomDrawer from '@components/app/CustomDrawer'
import CustomTextField from '@core/components/mui/TextField'

// Local Components
import AccountingPeriodKPI from '@views/(protected)/accounting/setup/period-management/components/AccountingPeriodKPI'
import AccountingPeriodTableActions from '@views/(protected)/accounting/setup/period-management/components/AccountingPeriodTableActions'
import AccountingPeriodForm from '@views/(protected)/accounting/setup/period-management/components/AccountingPeriodForm'

// Local Hooks
import {
  useAccountingPeriodsQuery,
  useFiscalYearsQuery,
  usePeriodManagementKpiQuery
} from '@/features/(protected)/accounting/setup/period-management/api/period-management.queries'
import { useAccountingPeriodController } from '@views/(protected)/accounting/setup/period-management/useAccountingPeriodController'

// Local Types
import {
  type AccountingPeriod,
  type AccountingPeriodsResponse,
  type FiscalYear,
  type PeriodStatus,
  type FiscalYearStatus,
  toUpdateFiscalYearPayload,
  toTransitionPeriodStatusPayload
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

// Local Utils
import getFiscalYearsTableColumns from '@views/(protected)/accounting/setup/period-management/components/FiscalYearsColumns'

import CustomDialog from '@components/app/CustomDialog'
import getPeriodsTableColumns from '@views/(protected)/accounting/setup/period-management/components/PeriodsColumns'
import AccountingPeriodSettingsForm from '@views/(protected)/accounting/setup/period-management/components/AccountingPeriodSettingsForm'
import FiscalYearSettingsForm from '@views/(protected)/accounting/setup/period-management/components/FiscalYearSettingsForm'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

function AccountingPeriodPage() {
  // Period Functions
  const { createYear, getCurrentYear, setPeriodStatus, updateFiscalYear, deleteFiscalYear } =
    useAccountingPeriodController()

  const access = useCurrentTenantAccess()
  const canCreateFiscalYears = access.can(APP_ABILITIES.periodCreate)
  const canEditPeriods = access.can(APP_ABILITIES.periodWrite)
  const canDeleteFiscalYears = access.can(APP_ABILITIES.periodDelete)
  const canManagePeriods = canCreateFiscalYears || canEditPeriods || canDeleteFiscalYears
  const hasLimitedPeriodAccess = canManagePeriods && !(canCreateFiscalYears && canEditPeriods && canDeleteFiscalYears)
  const periodManagementKpi = usePeriodManagementKpiQuery()

  const [fiscalYearData, setFiscalYearData] = useState<FiscalYear | null>(null)
  const [openFiscalYearDialog, setOpenFiscalYearDialog] = useState(false)
  const [fiscalYearToDelete, setFiscalYearToDelete] = useState<FiscalYear | null>(null)
  const [openDeleteFiscalYearDialog, setOpenDeleteFiscalYearDialog] = useState(false)
  const [deleteFiscalYearConfirmText, setDeleteFiscalYearConfirmText] = useState('')

  // Column Actions
  const openFiscalYearSettings = useCallback(
    (row: FiscalYear) => {
      if (!canEditPeriods) return

      setFiscalYearData(row)
      setOpenFiscalYearDialog(true)
    },
    [canEditPeriods]
  )

  const openDeleteFiscalYear = useCallback(
    (row: FiscalYear) => {
      if (!canDeleteFiscalYears) return

      setFiscalYearToDelete(row)
      setDeleteFiscalYearConfirmText('')
      setOpenDeleteFiscalYearDialog(true)
    },
    [canDeleteFiscalYears]
  )

  const closeDeleteFiscalYear = useCallback(() => {
    setOpenDeleteFiscalYearDialog(false)
    setFiscalYearToDelete(null)
    setDeleteFiscalYearConfirmText('')
  }, [])

  // Instantiate Fiscal Period Columns
  const fiscalYearsColumns = useMemo(
    () =>
      getFiscalYearsTableColumns({
        onEdit: row => openFiscalYearSettings(row),
        onDelete: row => openDeleteFiscalYear(row),
        canEditFiscalYears: canEditPeriods,
        canDeleteFiscalYears
      }),
    [canDeleteFiscalYears, canEditPeriods, openDeleteFiscalYear, openFiscalYearSettings]
  )

  const [periodData, setPeriodData] = useState<AccountingPeriod | null>(null)
  const [openPeriodDialog, setOpenPeriodDialog] = useState(false)

  // Column Actions
  const openPeriodSettings = useCallback(
    (row: AccountingPeriod) => {
      if (!canEditPeriods) return

      setPeriodData(row)
      setOpenPeriodDialog(true)
    },
    [canEditPeriods]
  )

  // Instantiate Per Period Columns
  const periodColumns = useMemo(
    () =>
      getPeriodsTableColumns({
        onLock: row => openPeriodSettings(row),
        canManagePeriodStatus: canEditPeriods
      }),
    [canEditPeriods, openPeriodSettings]
  )

  // Action Table states
  const [periodSearchValue, setPeriodSearchValue] = React.useState('')
  const [fiscalYearSearchValue, setFiscalYearSearchValue] = React.useState('')
  const [fiscalStatusTypeValue, setFiscalStatusTypeValue] = React.useState<FiscalYearStatus | ''>('')
  const [periodStatusTypeValue, setPeriodStatusTypeValue] = React.useState<PeriodStatus | ''>('')
  const [selectedFiscalYearId, setSelectedFiscalYearId] = React.useState('')
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })

  // Get all fiscalYears
  const fiscalYearsTable = useFiscalYearsQuery({
    search: fiscalYearSearchValue,
    status: fiscalStatusTypeValue,
    page: paginationModel.page,
    pageSize: paginationModel.pageSize
  })

  const fiscalYearOptions = useFiscalYearsQuery({
    page: 0,
    pageSize: 100
  })

  // Get all periods
  const periods = useAccountingPeriodsQuery<AccountingPeriodsResponse>({
    fiscal_year_id: selectedFiscalYearId || undefined,
    search: periodSearchValue,
    status: periodStatusTypeValue,
    page: paginationModel.page,
    pageSize: paginationModel.pageSize
  })

  const fiscalYearPeriodsForStats = useAccountingPeriodsQuery<AccountingPeriodsResponse>(
    {
      fiscal_year_id: fiscalYearData?.id,
      page: 0,
      pageSize: 100
    },
    {
      enabled: openFiscalYearDialog && !!fiscalYearData?.id
    }
  )

  const fiscalYearPeriodStats = useMemo(() => {
    if (!fiscalYearPeriodsForStats.data) return null

    const rows = fiscalYearPeriodsForStats.data.data
    const total = fiscalYearPeriodsForStats.data.total ?? rows.length
    const closed = rows.filter(period => period.status === 'closed').length

    return {
      total,
      closed
    }
  }, [fiscalYearPeriodsForStats.data])

  // Tab mode
  const [viewMode, setViewMode] = useState<'period' | 'fiscal_year'>('period')

  // Drawer States
  const [openDrawer, setOpenDrawer] = useState(false)
  const [drawerMode, setDrawerMode] = useState<'create' | 'update'>('create')
  const [open, setOpen] = useState(false)

  // Dialog State
  const [openDiscardDialog, setOpenDiscardDialog] = useState(false)

  // Form Dirty State
  const [isDirty, setIsDirty] = useState(false)

  const openCreate = () => {
    if (!canCreateFiscalYears) return

    // setFiscalYearData(null)
    setDrawerMode('create')
    setOpenDrawer(true)
    setOpen(true)
  }

  // const openEdit = useCallback((row: AccountingPeriod) => {
  //   // setFiscalYearData(row)
  //   setDrawerMode('update')
  //   setOpenDrawer(true)
  // }, [])

  // Discard Handler
  const handleDiscard = () => {
    setOpenDiscardDialog(false)
    setDrawerMode('create')
    setOpenDrawer(false)
    setOpen(false)
  }

  const closeDrawer = useCallback(() => {
    if (isDirty) {
      setOpenDiscardDialog(true)

      return
    }

    setDrawerMode('create')
    setOpenDrawer(false)
    setOpen(false)
  }, [isDirty])

  const closeOnSubmit = () => {
    setDrawerMode('create')
    setOpenDrawer(false)
    setOpen(false)
  }

  useEffect(() => {
    const options = fiscalYearOptions.data?.data ?? []

    if (options.length === 0) return

    const selectedIsValid = options.some(option => option.id === selectedFiscalYearId)

    if (selectedFiscalYearId && selectedIsValid) return

    const currentOption = options.find(option => option.is_current)
    const nextSelectedFiscalYearId = getCurrentYear.data?.id ?? currentOption?.id ?? options[0]?.id ?? ''

    if (nextSelectedFiscalYearId) {
      setSelectedFiscalYearId(nextSelectedFiscalYearId)
    }
  }, [fiscalYearOptions.data?.data, getCurrentYear.data, selectedFiscalYearId])

  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [viewMode])

  return (
    <Stack>
      <ToastContainer
        autoClose={2500}
        hideProgressBar={false}
        closeOnClick={true}
        transition={Bounce}
        position={'top-center'}
        pauseOnFocusLoss={false}
      />

      <CustomDialog
        open={openDeleteFiscalYearDialog}
        onClose={closeDeleteFiscalYear}
        title='Delete Fiscal Year'
        description={
          fiscalYearToDelete ? (
            <Stack spacing={2}>
              <Typography variant='body2'>You are about to permanently delete this fiscal year:</Typography>
              <Typography variant='body2'>
                <strong>{fiscalYearToDelete.code}</strong> — {fiscalYearToDelete.name}
              </Typography>
              <Typography variant='body2' color='warning.main'>
                Warning: This will be a delete cascade. All accounting periods under this fiscal year will also be
                permanently deleted.
              </Typography>
              <Typography variant='body2'>Type `delete` below to confirm.</Typography>
            </Stack>
          ) : null
        }
        actions={
          <>
            <Button variant='outlined' onClick={closeDeleteFiscalYear} disabled={deleteFiscalYear.isPending}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color='error'
              disabled={
                deleteFiscalYearConfirmText.trim() !== 'delete' || deleteFiscalYear.isPending || !fiscalYearToDelete
              }
              loading={deleteFiscalYear.isPending}
              onClick={async () => {
                if (!fiscalYearToDelete?.id) return

                await toast.promise(deleteFiscalYear.mutateAsync(fiscalYearToDelete.id), {
                  pending: 'Deleting fiscal year…',
                  success: 'Fiscal year deleted successfully.',
                  error: {
                    render({ data }) {
                      const err = data as any

                      return err?.message ?? 'Something went wrong.'
                    }
                  }
                })

                closeDeleteFiscalYear()
              }}
            >
              Confirm Delete
            </Button>
          </>
        }
        disableEscapeKeyDown={deleteFiscalYear.isPending}
        closeAfterTransition
        children={
          <CustomTextField
            fullWidth
            label='Confirmation'
            placeholder='Type delete'
            value={deleteFiscalYearConfirmText}
            onChange={event => setDeleteFiscalYearConfirmText(event.target.value)}
            disabled={deleteFiscalYear.isPending}
          />
        }
      />

      <CustomPageHeader title={'Period Management'} description={'Manage your fiscal year periods'}>
        {/* Fiscal Period Stats*/}
        <AccountingPeriodKPI kpi={periodManagementKpi.data ?? null} isLoading={periodManagementKpi.isFetching} />
      </CustomPageHeader>

      {!canManagePeriods ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          You can view fiscal years and periods only. Add, edit, change status, and delete actions appear only when your
          role grants those specific permissions.
        </Alert>
      ) : hasLimitedPeriodAccess ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Some period actions are limited by your role. Only the controls you can use are shown on this page.
        </Alert>
      ) : null}

      <CustomDrawer
        title={drawerMode === 'create' ? 'New Fiscal Year' : 'Edit Fiscal Year'}
        subtitle={drawerMode === 'create' ? 'Add a new fiscal period' : 'Edit fiscal year details'}
        open={openDrawer}
        onClose={closeDrawer}
        width={420}
      >
        {/* Accounting Discard Dialog*/}
        <CustomDialog
          title={'Discard Changes'}
          description={'Are you sure you want to discard your changes?'}
          actions={
            <>
              <Button variant='outlined' onClick={() => setOpenDiscardDialog(false)}>
                Cancel
              </Button>
              <Button variant='contained' color='error' onClick={handleDiscard}>
                Discard
              </Button>
            </>
          }
          closeAfterTransition
          onClose={() => setOpenDiscardDialog(false)}
          open={openDiscardDialog}
          children={null}
        />

        {/* Accounting Period Settings Dialog*/}
        <CustomDialog
          icon={<i className='bx-cog' style={{ fontSize: '26px' }} />}
          title={'Period Settings'}
          closeAfterTransition
          onClose={() => setOpenDiscardDialog(false)}
          open={openPeriodDialog}
          children={
            <AccountingPeriodSettingsForm
              isOpen={openPeriodDialog}
              onSubmit={async values => {
                await toast.promise(
                  setPeriodStatus.mutateAsync({
                    id: periodData?.id as string,
                    payload: toTransitionPeriodStatusPayload(values)
                  }),
                  {
                    pending: 'Saving period changes…',
                    success: 'Period status changed successfully.',
                    error: {
                      render({ data }) {
                        const err = data as any

                        return err?.message ?? 'Something went wrong.'
                      }
                    }
                  }
                )
                setOpenPeriodDialog(false)
              }}
              onDiscard={() => setOpenPeriodDialog(false)}
              periodData={periodData}
            />
          }
        />

        {/* Fiscal Year Settings Dialog*/}
        <CustomDialog
          icon={<i className='bx-cog' style={{ fontSize: '26px' }} />}
          title={'Fiscal Year Settings'}
          closeAfterTransition
          onClose={() => setOpenFiscalYearDialog(false)}
          open={openFiscalYearDialog}
          children={
            <FiscalYearSettingsForm
              isOpen={openFiscalYearDialog}
              periodStats={fiscalYearPeriodStats}
              isLoadingPeriodStats={fiscalYearPeriodsForStats.isFetching}
              onSubmit={async values => {
                await toast.promise(
                  updateFiscalYear.mutateAsync({
                    id: fiscalYearData?.id as string,
                    payload: toUpdateFiscalYearPayload(values)
                  }),
                  {
                    pending: 'Saving fiscal year changes…',
                    success: 'Fiscal year settings updated successfully.',
                    error: {
                      render({ data }) {
                        const err = data as any

                        return err?.message ?? 'Something went wrong.'
                      }
                    }
                  }
                )
                setOpenFiscalYearDialog(false)
              }}
              onDiscard={() => setOpenFiscalYearDialog(false)}
              fiscalYearData={fiscalYearData}
            />
          }
        />

        {/*Accounting Period Form*/}
        <AccountingPeriodForm
          actionType={drawerMode}
          onDiscard={closeDrawer}
          onSubmit={async values => {
            if (drawerMode === 'create') {
              await toast.promise(createYear.mutateAsync(values), {
                pending: 'Saving Period…',
                success: 'Period created',
                error: {
                  render({ data }) {
                    const err = data as any

                    return err?.message ?? 'Something went wrong.'
                  }
                }
              })
              closeOnSubmit()
            }
          }}
          isOpen={open}
          onDirtyChange={setIsDirty}
        />
      </CustomDrawer>

      {/*Table Actions*/}
      <AccountingPeriodTableActions
        viewMode={viewMode}
        onClick={openCreate}
        onSearchChange={value => {
          if (viewMode === 'period') {
            setPeriodSearchValue(value)
          } else {
            setFiscalYearSearchValue(value)
          }

          setPaginationModel(prev => ({ ...prev, page: 0 }))
        }}
        onFiscalStatusTypeChange={value => {
          setFiscalStatusTypeValue(value)
          setPaginationModel(prev => ({ ...prev, page: 0 }))
        }}
        onPeriodStatusTypeChange={value => {
          setPeriodStatusTypeValue(value)
          setPaginationModel(prev => ({ ...prev, page: 0 }))
        }}
        selectedFiscalYearId={selectedFiscalYearId}
        onFiscalYearChange={value => {
          setSelectedFiscalYearId(value)
          setPaginationModel(prev => ({ ...prev, page: 0 }))
        }}
        fiscalYearOptions={fiscalYearOptions.data?.data ?? []}
        searchValue={viewMode === 'period' ? periodSearchValue : fiscalYearSearchValue}
        fiscalStatusTypeValue={fiscalStatusTypeValue}
        periodStatusTypeValue={periodStatusTypeValue}
        canCreateFiscalYear={canCreateFiscalYears}
      />

      <Paper>
        <Tabs
          value={viewMode}
          onChange={(_, value: 'period' | 'fiscal_year') => setViewMode(value)}
          sx={{ pt: 2, px: 4 }}
        >
          <Tab icon={<i className={'bx-data'} />} iconPosition='start' label='Period' value='period' />
          <Tab icon={<i className={'bx-layer'} />} iconPosition='start' label='Fiscal Years' value='fiscal_year' />
        </Tabs>

        {viewMode === 'period' ? (
          <CustomTable
            columns={periodColumns}
            rows={periods?.data?.data ?? []}
            paginationMode={'server'}
            paginationModel={paginationModel}
            onPaginationModelChange={setPaginationModel}
            loading={periods.isFetching}
            rowCount={periods.data?.total ?? 0}
            getRowHeight={() => 'auto'}
            sx={{
              // Targets all cells in the DataGrid
              '& .MuiDataGrid-cell': {
                padding: '12px !important', // Adjust padding as needed
                alignItems: 'center' // or top, bottom
              }
            }}
          />
        ) : (
          <CustomTable
            columns={fiscalYearsColumns}
            rows={fiscalYearsTable?.data?.data ?? []}
            checkBoxSelection={canDeleteFiscalYears}
            paginationMode={'server'}
            paginationModel={paginationModel}
            onPaginationModelChange={setPaginationModel}
            loading={fiscalYearsTable.isFetching}
            rowCount={fiscalYearsTable.data?.total ?? 0}
            getRowHeight={() => 56}
          />
        )}
      </Paper>
    </Stack>
  )
}

export default AccountingPeriodPage
