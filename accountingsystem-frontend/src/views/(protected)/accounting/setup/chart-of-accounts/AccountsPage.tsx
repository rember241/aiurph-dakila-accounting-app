'use client'

import React, { useCallback, useEffect, useMemo, useState } from 'react'

import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import type { GridPaginationModel, GridSortModel } from '@mui/x-data-grid'

import { Bounce, ToastContainer, toast } from 'react-toastify'

import CustomPageHeader from '@components/app/CustomPageHeader'
import CustomTable from '@components/app/CustomTable'
import CustomDrawer from '@components/app/CustomDrawer'
import AccountsForm from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountsForm'
import AccountsTableActions from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountsTableActions'
import AccountsTree from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountsTree'
import { getAccountTableColumns } from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountTableColumns'

import {
  useAccountsQuery,
  useAccountsTreeQuery
} from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'

import type {
  AccountColumn,
  AccountStatus,
  AccountFilter,
  AccountPosting,
  AccountTreeNode
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import {
  ACCOUNT_CATEGORY_FILTERS,
  ACCOUNT_CORE_TYPE_ORDER
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import {
  toDefaultValues,
  toPayload
} from '@/features/(protected)/accounting/setup/chart-of-accounts/mappers/accounts.mappers'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { useAccountsController } from '@views/(protected)/accounting/setup/chart-of-accounts/useAccountsController'
import CustomDialog from '@components/app/CustomDialog'

import { DeleteModalDescription } from '@views/(protected)/accounting/setup/chart-of-accounts/components/DeleteModalDescription'

import AccountsHeaderStats from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountsHeaderStats'
import AccountDetails from '@views/(protected)/accounting/setup/chart-of-accounts/components/AccountDetails'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { extractLaravelErrorMessage } from '@/lib/axios/auth/laravel-browser-client'

type FlattenedTreeNode = AccountTreeNode & { level: number; has_children: boolean }

function flattenTree(nodes: AccountTreeNode[], level = 0): FlattenedTreeNode[] {
  return nodes.flatMap(node => [
    { ...node, level, has_children: (node.children ?? []).length > 0 },
    ...flattenTree(node.children ?? [], level + 1)
  ])
}

export function AccountsPage() {
  const { create, update, remove, stats } = useAccountsController()
  const access = useCurrentTenantAccess()
  const canCreateAccounts = access.can(APP_ABILITIES.accountingCreate)
  const canEditAccounts = access.can(APP_ABILITIES.accountingWrite)
  const canDeleteAccounts = access.can(APP_ABILITIES.accountingDelete)
  const canManageAccounts = canCreateAccounts || canEditAccounts || canDeleteAccounts
  const hasLimitedAccountAccess = canManageAccounts && !(canCreateAccounts && canEditAccounts && canDeleteAccounts)

  // Modal state
  const [removeModalOpen, setRemoveModalOpen] = useState(false)
  const [viewingModalOpen, setViewingModalOpen] = useState(false)
  const [viewing, setViewing] = useState<AccountColumn | null>(null)
  const [removing, setRemoving] = useState<AccountColumn | null>(null)

  const openRemoveModal = useCallback(
    (row: AccountColumn) => {
      if (!canDeleteAccounts) return

      setRemoving(row)
      setRemoveModalOpen(true)
    },
    [canDeleteAccounts]
  )

  const openViewModal = useCallback((row: AccountColumn) => {
    setViewing(row)
    setViewingModalOpen(true)
  }, [])

  const closeViewModal = useCallback(() => {
    setViewingModalOpen(false)
  }, [])

  const closeRemoveModal = useCallback(() => {
    setRemoveModalOpen(false)
    setRemoving(null)
  }, [])

  // remove Callback
  const handleConfirmRemove = useCallback(async () => {
    if (!removing?.id) return

    try {
      await toast.promise(remove.mutateAsync(removing.id), {
        pending: 'Deleting account…',
        success: 'Account removed',
        error: {
          render({ data }) {
            return extractLaravelErrorMessage(data, 'Unable to delete this account.')
          }
        }
      })

      closeRemoveModal()
    } catch {
      // keep modal open so user can retry
    }
  }, [removing?.id, remove, closeRemoveModal])

  // Drawer state
  const [drawerMode, setDrawerMode] = useState<'create' | 'update'>('create')
  const [editing, setEditing] = useState<AccountColumn | null>(null)
  const [open, setOpen] = useState(false)

  const openCreate = () => {
    if (!canCreateAccounts) return

    setEditing(null)
    setDrawerMode('create')
    setOpen(true)
  }

  const openEdit = useCallback(
    (row: AccountColumn) => {
      if (!canEditAccounts) return

      setEditing(row)
      setDrawerMode('update')
      setOpen(true)
    },
    [canEditAccounts]
  )

  const closeDrawer = useCallback(() => {
    setOpen(false)
    setEditing(null)
    setDrawerMode('create')
  }, [])

  // DATAGrid Columns
  const columns = useMemo(
    () =>
      getAccountTableColumns({
        onView: row => openViewModal(row),
        onEdit: row => openEdit(row),
        onDelete: row => openRemoveModal(row),
        canEditAccounts,
        canDeleteAccounts
      }),
    [canDeleteAccounts, canEditAccounts, openRemoveModal, openViewModal, openEdit]
  )

  // Filters + pagination
  const [q, setQ] = useState('')
  const qDebounced = useDebouncedValue(q, 400)

  const [accountFilter, setAccountFilter] = useState<AccountFilter>('')
  const type = ACCOUNT_CORE_TYPE_ORDER.find(coreType => coreType === accountFilter)
  const category = ACCOUNT_CATEGORY_FILTERS.find(option => option.value === accountFilter)?.value
  const [status, setStatus] = useState<AccountStatus | ''>('')
  const [posting, setPosting] = useState<AccountPosting>()
  const [viewMode, setViewMode] = useState<'table' | 'tree'>('table')

  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: 'asc' }])
  const primarySort = sortModel[0]

  const accounts = useAccountsQuery({
    q: qDebounced.toLowerCase(),
    type,
    category,
    status,
    is_posting: posting,
    page: paginationModel.page,
    pageSize: paginationModel.pageSize,
    sortField: primarySort?.field,
    sortDir: (primarySort?.sort as any) ?? 'asc'
  })

  const accountsTree = useAccountsTreeQuery(
    {
      q: qDebounced.toLowerCase(),
      type,
      category,
      status,
      is_posting: posting
    },
    { enabled: viewMode === 'tree' }
  )

  const allAccountsTree = useAccountsTreeQuery({}, { enabled: open })

  const contraOptionsQuery = useAccountsQuery({
    page: 0,
    pageSize: 100,
    sortField: 'name',
    sortDir: 'asc'
  })

  const contraOptions = useMemo(
    () =>
      (contraOptionsQuery.data?.data ?? []).map((account: AccountColumn) => ({
        id: account.id,
        code: account.code,
        name: account.name
      })),
    [contraOptionsQuery.data]
  )

  const parentOptions = useMemo(
    () =>
      flattenTree(allAccountsTree.data?.data ?? [])
        .filter(account => account.id !== editing?.id)
        .map(account => ({
          id: account.id,
          code: account.code,
          name: account.name,
          type: account.type,
          level: account.level,
          is_posting: account.is_posting,
          has_children: account.has_children
        })),
    [allAccountsTree.data?.data, editing?.id]
  )

  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [qDebounced, status, posting, primarySort?.field, primarySort?.sort])

  const defaultValues = useMemo(() => toDefaultValues(editing ?? undefined), [editing])

  const isSubmitting = create.isPending || update.isPending

  return (
    <>
      <ToastContainer
        autoClose={2500}
        hideProgressBar={false}
        closeOnClick={true}
        transition={Bounce}
        position={'top-center'}
        pauseOnFocusLoss={false}
      />

      <CustomDialog
        open={removeModalOpen}
        onClose={closeRemoveModal}
        title='Delete Account'
        description={DeleteModalDescription(removing)}
        actions={
          <>
            <Button variant='outlined' onClick={closeRemoveModal} disabled={remove.isPending}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color='error'
              onClick={handleConfirmRemove}
              disabled={!removing?.id || remove.isPending}
              loading={remove.isPending}
            >
              Delete
            </Button>
          </>
        }
        disableEscapeKeyDown={remove.isPending}
        closeAfterTransition
        children={null}
      />

      <CustomDialog
        open={viewingModalOpen}
        onClose={closeViewModal}
        title='Account Details'
        description={''}
        actions={
          <>
            <Button variant='outlined' onClick={closeViewModal}>
              Close
            </Button>
          </>
        }
        disableEscapeKeyDown={true}
        closeAfterTransition
        children={<AccountDetails account={viewing} />}
      />

      <CustomPageHeader title={'Chart of Accounts'} description={'Manage your account structure'}>
        <AccountsHeaderStats stats={stats?.data?.data} loading={stats.isLoading} error={stats.error?.message} />
      </CustomPageHeader>

      {!canManageAccounts ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          You can view accounts only. Add, edit, and delete actions appear only when your role grants those specific
          permissions.
        </Alert>
      ) : hasLimitedAccountAccess ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Some account actions are limited by your role. Only the controls you can use are shown on this page.
        </Alert>
      ) : null}

      <CustomDrawer
        title={drawerMode === 'create' ? 'Create New Account' : 'Edit Account'}
        subtitle={drawerMode === 'create' ? 'Add a new account to your chart' : 'Change the details of this account'}
        onClose={closeDrawer}
        open={open}
        width={450}
      >
        <AccountsForm
          actionType={drawerMode}
          onSubmit={async values => {
            if (drawerMode === 'create') {
              await toast.promise(create.mutateAsync(toPayload(values)), {
                pending: 'Saving account…',
                success: 'Account created',
                error: {
                  render({ data }) {
                    return extractLaravelErrorMessage(data, 'Unable to create account.')
                  }
                }
              })
              closeDrawer()
            } else if (editing) {
              await toast.promise(update.mutateAsync({ id: editing.id, payload: toPayload(values, editing) }), {
                pending: 'Updating account…',
                success: 'Account updated',
                error: {
                  render({ data }) {
                    return extractLaravelErrorMessage(data, 'Unable to update account.')
                  }
                }
              })
              closeDrawer()
            }
          }}
          onDiscard={closeDrawer}
          isSubmitting={isSubmitting}
          defaultValues={defaultValues}
          contraOptions={contraOptions}
          parentOptions={parentOptions}
          isOpen={open}
        />
      </CustomDrawer>

      <AccountsTableActions
        statusValue={status}
        onStatusChange={setStatus}
        postingValue={posting}
        onPostingChange={setPosting}
        searchValue={q}
        onSearchChange={setQ}
        accountFilter={accountFilter}
        onAccountFilterChange={value => {
          setAccountFilter(value)
          setPaginationModel(previous => ({ ...previous, page: 0 }))
        }}
        onClick={openCreate}
        canCreateAccount={canCreateAccounts}
      />

      <Paper sx={{ mb: 3 }}>
        <Tabs value={viewMode} onChange={(_, value: 'table' | 'tree') => setViewMode(value)} sx={{ pt: 2, px: 4 }}>
          <Tab icon={<i className={'bx-data'} />} iconPosition='start' label='Table View' value='table' />
          <Tab icon={<i className={'bx-layer'} />} iconPosition='start' label='Tree View' value='tree' />
        </Tabs>
        {viewMode === 'table' ? (
          <CustomTable<AccountColumn>
            rows={accounts.data?.data ?? []}
            columns={columns}
            loading={accounts.isFetching}
            getRowId={r => r.id}
            showToolbar={false}
            paginationMode='server'
            sortingMode='server'
            rowCount={accounts.data?.total ?? 0}
            paginationModel={paginationModel}
            onPaginationModelChange={setPaginationModel}
            sortModel={sortModel}
            onSortModelChange={setSortModel}
            checkBoxSelection={canDeleteAccounts}
            isRowSelectable={params => params.row.status === 'active'}
          />
        ) : (
          <AccountsTree
            nodes={accountsTree.data?.data ?? []}
            loading={accountsTree.isFetching}
            onView={row => openViewModal(row)}
            onEdit={row => openEdit(row)}
            onDelete={row => openRemoveModal(row)}
            canEditAccounts={canEditAccounts}
            canDeleteAccounts={canDeleteAccounts}
          />
        )}
      </Paper>
    </>
  )
}
