'use client'

import { useCallback, useMemo, useState } from 'react'

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import type { GridRowSelectionModel, GridSortModel } from '@mui/x-data-grid'
import { Bounce, toast, ToastContainer } from 'react-toastify'

import CustomDialog from '@components/app/CustomDialog'
import CustomPageHeader from '@components/app/CustomPageHeader'

import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { RoleApiError } from '@/features/(protected)/users/roles/api/roles.api'
import { type RoleFormSubmitValues, roleFormSchema } from '@/features/(protected)/users/roles/model/roles.schema'
import {
  canDeleteRole,
  canEditRole,
  canSelectRoleForBulk,
  getRoleDeleteGuardReason,
  getRoleEditGuardReason
} from '@/features/(protected)/users/roles/model/roles.types'
import type { RoleAccessLevel, RoleRecord, RoleStatus } from '@/features/(protected)/users/roles/model/roles.types'
import RoleFormDrawer from '@views/(protected)/users/roles/components/RoleFormDrawer'
import RolesFilters from '@views/(protected)/users/roles/components/RolesFilters'
import { getRoleTableColumns } from '@views/(protected)/users/roles/components/RoleTableColumns'
import RolesTable from '@views/(protected)/users/roles/components/RolesTable'
import { useRolesController } from '@views/(protected)/users/roles/useRolesController'
import UserManagementSkeleton from '@views/(protected)/users/user-management/components/UserManagementSkeleton'
import UserManagementStateCard from '@views/(protected)/users/user-management/components/UserManagementStateCard'
import AccessControlManual from '@views/(protected)/users/components/AccessControlManual'
import PermissionsReference from '@views/(protected)/users/components/PermissionsReference'
import CustomKPICard from '@components/app/CustomKPICard'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

type ConfirmAction =
  | {
      type: 'status'
      ids: string[]
      nextStatus: RoleStatus
      title: string
      description: string
      confirmLabel: string
      confirmColor: 'success' | 'error'
    }
  | {
      type: 'access'
      ids: string[]
      nextAccess: RoleAccessLevel
      title: string
      description: string
      confirmLabel: string
    }

function toErrorMessage(error: unknown, fallback: string) {
  if (error instanceof RoleApiError || error instanceof Error) {
    return error.message
  }

  return fallback
}

function RolesPage() {
  const access = useCurrentTenantAccess()
  const canCreateRoles = access.can(APP_ABILITIES.rolesCreate)
  const canEditRoles = access.can(APP_ABILITIES.rolesWrite)
  const canDeleteRoles = access.can(APP_ABILITIES.rolesDelete)
  const canReadOrgUnits = access.can(APP_ABILITIES.orgUnitsRead)
  const canManageRoles = canCreateRoles || canEditRoles || canDeleteRoles

  const {
    roles,
    stats,
    error,
    isBootstrapping,
    isRefreshing,
    isSaving: isMutating,
    orgUnitOptions,
    createRole,
    updateRole,
    setRolesStatus,
    setRolesAccess,
    deleteRole
  } = useRolesController({ canReadOrgUnits })

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<RoleStatus | ''>('')
  const [accessFilter, setAccessFilter] = useState<RoleAccessLevel | ''>('')
  const [orgUnitFilter, setOrgUnitFilter] = useState('')
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: 'asc' }])

  const [rowSelectionModel, setRowSelectionModel] = useState<GridRowSelectionModel>({
    type: 'include',
    ids: new Set()
  })

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingRole, setEditingRole] = useState<RoleRecord | null>(null)
  const [removingRole, setRemovingRole] = useState<RoleRecord | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null)

  const debouncedSearch = useDebouncedValue(search.trim().toLowerCase(), 350)

  const filteredRoles = useMemo(() => {
    return roles.filter(role => {
      const matchesSearch =
        debouncedSearch.length === 0 ||
        role.name.toLowerCase().includes(debouncedSearch) ||
        role.key.toLowerCase().includes(debouncedSearch) ||
        (role.orgUnit?.name ?? '').toLowerCase().includes(debouncedSearch)

      const matchesStatus = statusFilter === '' || role.status === statusFilter
      const matchesAccess = accessFilter === '' || role.accessLevel === accessFilter

      const matchesOrgUnit =
        orgUnitFilter === ''
          ? true
          : orgUnitFilter === '__company__'
            ? role.orgUnit === null
            : role.orgUnit?.id === orgUnitFilter

      return matchesSearch && matchesStatus && matchesAccess && matchesOrgUnit
    })
  }, [roles, debouncedSearch, statusFilter, accessFilter, orgUnitFilter])

  const selectedIds = useMemo(() => Array.from(rowSelectionModel.ids, value => String(value)), [rowSelectionModel])

  const hasAnyFilter = search.length > 0 || statusFilter !== '' || accessFilter !== '' || orgUnitFilter !== ''

  const errorMessage = actionError ?? (error instanceof Error ? error.message : null)
  const enabledCount = stats.active
  const disabledCount = stats.inactive

  const clearFilters = useCallback(() => {
    setSearch('')
    setStatusFilter('')
    setAccessFilter('')
    setOrgUnitFilter('')
  }, [])

  const closeForm = useCallback(() => {
    setIsFormOpen(false)
    setEditingRole(null)
  }, [])

  const openCreateForm = useCallback(() => {
    if (!canCreateRoles) {
      return
    }

    setActionError(null)
    setEditingRole(null)
    setIsFormOpen(true)
  }, [canCreateRoles])

  const openEditForm = useCallback(
    (role: RoleRecord) => {
      if (!canEditRoles) {
        return
      }

      const editGuardReason = getRoleEditGuardReason(role)

      if (!canEditRole(role)) {
        setActionError(editGuardReason ?? 'This role cannot be edited.')

        return
      }

      setActionError(null)
      setEditingRole(role)
      setIsFormOpen(true)
    },
    [canEditRoles]
  )

  const openDeleteDialog = useCallback(
    (role: RoleRecord) => {
      if (!canDeleteRoles) {
        return
      }

      const deleteGuardReason = getRoleDeleteGuardReason(role)

      if (!canDeleteRole(role)) {
        setActionError(deleteGuardReason ?? 'This role cannot be deleted.')

        return
      }

      setActionError(null)
      setRemovingRole(role)
    },
    [canDeleteRoles]
  )

  const closeDeleteDialog = useCallback(() => {
    setRemovingRole(null)
  }, [])

  const requestStatusChange = useCallback(
    (ids: string[], nextStatus: RoleStatus) => {
      if (!canEditRoles) return
      if (!ids.length) return

      const isSingle = ids.length === 1

      setConfirmAction({
        type: 'status',
        ids,
        nextStatus,
        title: nextStatus === 'enabled' ? 'Activate Role' : 'Deactivate Role',
        description: isSingle
          ? `This will ${nextStatus === 'enabled' ? 'activate' : 'deactivate'} the selected role.`
          : `This will ${nextStatus === 'enabled' ? 'activate' : 'deactivate'} ${ids.length} selected roles.`,
        confirmLabel: nextStatus === 'enabled' ? 'Activate' : 'Deactivate',
        confirmColor: nextStatus === 'enabled' ? 'success' : 'error'
      })
    },
    [canEditRoles]
  )

  const requestAccessChange = useCallback(
    (ids: string[], nextAccess: RoleAccessLevel) => {
      if (!canEditRoles) return
      if (!ids.length) return

      const isSingle = ids.length === 1

      setConfirmAction({
        type: 'access',
        ids,
        nextAccess,
        title: 'Update Role Access',
        description: isSingle
          ? `This will set the selected role to ${nextAccess.toUpperCase()} access.`
          : `This will set ${ids.length} selected roles to ${nextAccess.toUpperCase()} access.`,
        confirmLabel: `Set ${nextAccess.toUpperCase()} Access`
      })
    },
    [canEditRoles]
  )

  const handleConfirmAction = useCallback(() => {
    if (!confirmAction) return

    const run = async () => {
      const action = confirmAction
      const isSingle = action.ids.length === 1

      setActionError(null)

      try {
        await toast.promise(
          action.type === 'status'
            ? setRolesStatus(action.ids, action.nextStatus)
            : setRolesAccess(action.ids, action.nextAccess),
          {
            pending:
              action.type === 'status'
                ? `${action.nextStatus === 'enabled' ? 'Activating' : 'Deactivating'} ${isSingle ? 'role' : 'roles'}…`
                : `Updating ${isSingle ? 'role' : 'roles'} access…`,
            success:
              action.type === 'status'
                ? `${isSingle ? 'Role' : 'Roles'} ${action.nextStatus === 'enabled' ? 'activated' : 'deactivated'}`
                : `${isSingle ? 'Role' : 'Roles'} access updated`,
            error: {
              render({ data }: { data: unknown }) {
                return toErrorMessage(
                  data,
                  action.type === 'status' ? 'Unable to update role status.' : 'Unable to update role access.'
                )
              }
            }
          }
        )

        setRowSelectionModel({ type: 'include', ids: new Set() })
        setConfirmAction(null)
      } catch {
        // Keep the dialog open so the user can retry after the toast error.
      }
    }

    void run()
  }, [confirmAction, setRolesStatus, setRolesAccess])

  const columns = useMemo(
    () =>
      getRoleTableColumns({
        onSettings: openEditForm,
        onDelete: openDeleteDialog,
        canEditRoles,
        canDeleteRoles
      }),
    [canDeleteRoles, canEditRoles, openDeleteDialog, openEditForm]
  )

  const handleConfirmDelete = useCallback(async () => {
    if (!removingRole) return

    if (!canDeleteRoles) {
      setActionError('You do not have permission to delete roles in this workspace.')

      return
    }

    try {
      await toast.promise(deleteRole(removingRole.id), {
        pending: 'Deleting role…',
        success: 'Role removed',
        error: {
          render({ data }: { data: unknown }) {
            return data instanceof Error ? data.message : 'Unable to delete role.'
          }
        }
      })

      closeDeleteDialog()
    } catch {
      // Keep the dialog open so the user can retry.
    }
  }, [canDeleteRoles, closeDeleteDialog, deleteRole, removingRole])

  const handleSubmitRole = useCallback(
    async (values: RoleFormSubmitValues) => {
      const payload = roleFormSchema.parse(values)
      const isEditing = !!editingRole

      setIsSaving(true)
      setActionError(null)

      try {
        if (isEditing && !canEditRoles) {
          throw new Error('You do not have permission to update roles in this workspace.')
        }

        if (!isEditing && !canCreateRoles) {
          throw new Error('You do not have permission to create roles in this workspace.')
        }

        await toast.promise(isEditing ? updateRole(editingRole.id, payload) : createRole(payload), {
          pending: isEditing ? 'Updating role…' : 'Creating role…',
          success: isEditing ? 'Role updated' : 'Role created',
          error: {
            render({ data }: { data: unknown }) {
              return toErrorMessage(data, isEditing ? 'Unable to update role.' : 'Unable to create role.')
            }
          }
        })

        closeForm()
      } catch (mutationError) {
        setActionError(toErrorMessage(mutationError, isEditing ? 'Unable to update role.' : 'Unable to create role.'))
      } finally {
        setIsSaving(false)
      }
    },
    [canCreateRoles, canEditRoles, editingRole, updateRole, createRole, closeForm]
  )

  const bulkActionBar = canEditRoles && selectedIds.length > 0 && (
    <Alert
      severity='info'
      icon={false}
      sx={{ alignItems: 'center' }}
      action={
        <Stack direction='row' spacing={1}>
          <Button size='small' color='success' onClick={() => requestStatusChange(selectedIds, 'enabled')}>
            Activate
          </Button>
          <Button size='small' color='info' onClick={() => requestAccessChange(selectedIds, 'read')}>
            Read Access
          </Button>
          <Button size='small' color='primary' onClick={() => requestAccessChange(selectedIds, 'write')}>
            Write Access
          </Button>
          <Button size='small' color='error' onClick={() => requestStatusChange(selectedIds, 'disabled')}>
            Deactivate
          </Button>
        </Stack>
      }
    >
      <Stack direction='row' spacing={2}>
        <Box component='i' className={'bx-error-circle'} sx={{ fontSize: 20, lineHeight: 1.2, mt: 0.2 }} />
        <Typography variant='body2'>{selectedIds.length} role(s) selected for bulk actions.</Typography>
      </Stack>
    </Alert>
  )

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

      <CustomPageHeader title='Roles' description='Manage ERP role definitions and permissions'>
        <Stack direction='row' spacing={4}>
          <CustomKPICard
            label={`Active roles`}
            value={enabledCount}
            loading={isBootstrapping}
            iconString={'bx-shield-quarter'}
          >
            <Typography variant='h6' color='text.secondary'>
              Total roles: {stats.total}
            </Typography>
          </CustomKPICard>
          <CustomKPICard
            label={`Inactive roles`}
            value={disabledCount}
            loading={isBootstrapping}
            iconString={'bx-shield-quarter'}
            iconColor={'error'}
          />
        </Stack>
      </CustomPageHeader>

      <RolesFilters
        searchValue={search}
        onSearchChange={setSearch}
        statusValue={statusFilter}
        onStatusChange={setStatusFilter}
        accessValue={accessFilter}
        onAccessChange={setAccessFilter}
        orgUnitValue={orgUnitFilter}
        onOrgUnitChange={setOrgUnitFilter}
        orgUnitOptions={orgUnitOptions}
        onClearFilters={clearFilters}
        onCreateRole={openCreateForm}
        canCreateRole={canCreateRoles}
      />

      {!canManageRoles ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          You have view-only access in this workspace. Role changes appear only when your role grants create, edit, or
          delete access.
        </Alert>
      ) : null}

      {canManageRoles && !canReadOrgUnits ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Company-structure read access is not available for this role, so org unit role context options are limited to
          company-wide assignments.
        </Alert>
      ) : null}

      <AccessControlManual />
      <PermissionsReference />

      {errorMessage ? (
        <Alert severity='error' sx={{ mb: 3 }}>
          {errorMessage}
        </Alert>
      ) : null}

      {isBootstrapping ? (
        <UserManagementSkeleton />
      ) : roles.length === 0 ? (
        <UserManagementStateCard
          iconClassName='bx bx-shield-plus'
          title='No roles yet'
          description='Create your first role to start assigning role-based access in the ERP system.'
          actionLabel={canCreateRoles ? 'Create Role' : undefined}
          onAction={canCreateRoles ? openCreateForm : undefined}
        />
      ) : filteredRoles.length === 0 ? (
        <UserManagementStateCard
          iconClassName='bx bx-search-alt'
          title='No matching roles found'
          description='No roles matched your current search and filter criteria. Clear filters to see all roles.'
          actionLabel='Reset Filters'
          onAction={clearFilters}
        />
      ) : (
        <Paper>
          <Box sx={{ p: 5, pb: 0 }}>{bulkActionBar}</Box>
          <RolesTable
            rows={filteredRoles}
            columns={columns}
            loading={isRefreshing}
            sortModel={sortModel}
            onSortModelChange={setSortModel}
            enableRowSelection={canEditRoles}
            isRowSelectable={({ row }) => canEditRoles && canSelectRoleForBulk(row)}
            rowSelectionModel={rowSelectionModel}
            onRowSelectionModelChange={setRowSelectionModel}
          />
        </Paper>
      )}

      {hasAnyFilter ? (
        <Stack direction='row' justifyContent='flex-end' sx={{ mt: 2 }}>
          <Typography variant='caption' color='text.secondary'>
            Filters applied
          </Typography>
        </Stack>
      ) : null}

      <RoleFormDrawer
        open={isFormOpen}
        mode={editingRole ? 'edit' : 'create'}
        defaultRole={editingRole}
        orgUnitOptions={orgUnitOptions}
        isSubmitting={isSaving}
        onClose={closeForm}
        onSubmit={handleSubmitRole}
      />

      <CustomDialog
        open={!!removingRole}
        onClose={closeDeleteDialog}
        title='Delete Role'
        description={
          removingRole
            ? `Delete ${removingRole.name}? This action cannot be undone and the role will no longer be available for company assignments.`
            : ''
        }
        closeAfterTransition
        disableEscapeKeyDown={false}
        actions={
          <>
            <Button variant='outlined' color='secondary' onClick={closeDeleteDialog}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color='error'
              onClick={handleConfirmDelete}
              disabled={!removingRole || isMutating}
            >
              Delete Role
            </Button>
          </>
        }
      >
        {null}
      </CustomDialog>

      <CustomDialog
        open={!!confirmAction}
        onClose={() => setConfirmAction(null)}
        title={confirmAction?.title ?? 'Confirm Action'}
        description={confirmAction?.description ?? ''}
        closeAfterTransition
        disableEscapeKeyDown={false}
        actions={
          <>
            <Button variant='outlined' color='secondary' onClick={() => setConfirmAction(null)}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color={confirmAction?.type === 'status' ? confirmAction.confirmColor : 'primary'}
              onClick={handleConfirmAction}
              disabled={isSaving || isMutating}
            >
              {confirmAction?.confirmLabel ?? 'Confirm'}
            </Button>
          </>
        }
      >
        {null}
      </CustomDialog>
    </>
  )
}

export default RolesPage
