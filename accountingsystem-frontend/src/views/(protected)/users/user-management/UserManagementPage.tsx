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
import CustomKPICard from '@components/app/CustomKPICard'

import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { UserManagementApiError } from '@/features/(protected)/users/user-management/api/user-management.api'
import {
  type UserFormSubmitValues,
  userFormSchema
} from '@/features/(protected)/users/user-management/model/user-management.schema'
import type {
  UserAccessLevel,
  UserRecord,
  UserStatus
} from '@/features/(protected)/users/user-management/model/user-management.types'
import UserFormDrawer from '@views/(protected)/users/user-management/components/UserFormDrawer'
import UserManagementFilters from '@views/(protected)/users/user-management/components/UserManagementFilters'
import UserManagementSkeleton from '@views/(protected)/users/user-management/components/UserManagementSkeleton'
import UserManagementStateCard from '@views/(protected)/users/user-management/components/UserManagementStateCard'
import UserManagementTable from '@views/(protected)/users/user-management/components/UserManagementTable'
import UserProfilePanel from '@views/(protected)/users/user-management/components/UserProfilePanel'
import { getUserTableColumns } from '@views/(protected)/users/user-management/components/UserTableColumns'
import { useUserManagementController } from '@views/(protected)/users/user-management/useUserManagementController'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

type ConfirmAction = {
  type: 'status'
  ids: string[]
  nextStatus: UserStatus
  title: string
  description: string
  confirmLabel: string
  confirmColor: 'success' | 'error'
}

function toErrorMessage(error: unknown, fallback: string) {
  if (error instanceof UserManagementApiError || error instanceof Error) {
    return error.message
  }

  return fallback
}

function UserManagementPage() {
  const access = useCurrentTenantAccess()
  const canCreateUsers = access.can(APP_ABILITIES.usersCreate)
  const canEditUsers = access.can(APP_ABILITIES.usersWrite)
  const canDeleteUsers = access.can(APP_ABILITIES.usersDelete)
  const canReadRoles = access.can(APP_ABILITIES.rolesRead)
  const canReadOrgUnits = access.can(APP_ABILITIES.orgUnitsRead)
  const canManageUsers = canCreateUsers || canEditUsers || canDeleteUsers

  const {
    users,
    stats,
    error,
    isBootstrapping,
    isSaving,
    createUser,
    updateUser,
    setUsersStatus,
    deleteUser,
    roleOptions,
    orgUnitOptions
  } = useUserManagementController({ canReadRoles, canReadOrgUnits })

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<UserStatus | ''>('')
  const [accessFilter, setAccessFilter] = useState<UserAccessLevel | ''>('')
  const [roleFilter, setRoleFilter] = useState('')
  const [primaryOrgUnitFilter, setPrimaryOrgUnitFilter] = useState('')
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'fullName', sort: 'asc' }])

  const [rowSelectionModel, setRowSelectionModel] = useState<GridRowSelectionModel>({
    type: 'include',
    ids: new Set()
  })

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingUser, setEditingUser] = useState<UserRecord | null>(null)
  const [profileUser, setProfileUser] = useState<UserRecord | null>(null)
  const [removingUser, setRemovingUser] = useState<UserRecord | null>(null)
  const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null)
  const [pageError, setPageError] = useState<string | null>(null)

  const debouncedSearch = useDebouncedValue(search.trim().toLowerCase(), 350)

  const filteredUsers = useMemo(() => {
    return users.filter(user => {
      const matchesSearch =
        debouncedSearch.length === 0 ||
        user.fullName.toLowerCase().includes(debouncedSearch) ||
        user.email.toLowerCase().includes(debouncedSearch) ||
        user.role.toLowerCase().includes(debouncedSearch) ||
        user.primaryOrgUnitLabel.toLowerCase().includes(debouncedSearch) ||
        user.operatingScopeLabel.toLowerCase().includes(debouncedSearch) ||
        user.additionalOrgUnits.some(orgUnit => orgUnit.name.toLowerCase().includes(debouncedSearch))

      const matchesStatus = statusFilter === '' || user.status === statusFilter
      const matchesAccess = accessFilter === '' || user.accessLevel === accessFilter
      const matchesRole = roleFilter === '' || user.roleIds.includes(roleFilter)
      const matchesPrimaryOrgUnit = primaryOrgUnitFilter === '' || user.primaryOrgUnit?.id === primaryOrgUnitFilter

      return matchesSearch && matchesStatus && matchesAccess && matchesRole && matchesPrimaryOrgUnit
    })
  }, [users, debouncedSearch, statusFilter, accessFilter, roleFilter, primaryOrgUnitFilter])

  const selectedIds = useMemo(() => Array.from(rowSelectionModel.ids, value => String(value)), [rowSelectionModel])

  const hasAnyFilter =
    search.length > 0 || statusFilter !== '' || accessFilter !== '' || roleFilter !== '' || primaryOrgUnitFilter !== ''

  const clearFilters = useCallback(() => {
    setSearch('')
    setStatusFilter('')
    setAccessFilter('')
    setRoleFilter('')
    setPrimaryOrgUnitFilter('')
  }, [])

  const closeForm = useCallback(() => {
    setIsFormOpen(false)
    setEditingUser(null)
    setPageError(null)
  }, [])

  const openCreateForm = useCallback(() => {
    if (
      !canCreateUsers ||
      !canReadRoles ||
      !canReadOrgUnits ||
      roleOptions.length === 0 ||
      orgUnitOptions.length === 0
    ) {
      return
    }

    setEditingUser(null)
    setPageError(null)
    setIsFormOpen(true)
  }, [canCreateUsers, canReadOrgUnits, canReadRoles, orgUnitOptions.length, roleOptions.length])

  const openEditForm = useCallback(
    (user: UserRecord) => {
      if (!canEditUsers || !canReadRoles || !canReadOrgUnits) {
        return
      }

      if (!user.canEdit) {
        return
      }

      setEditingUser(user)
      setPageError(null)
      setIsFormOpen(true)
    },
    [canEditUsers, canReadOrgUnits, canReadRoles]
  )

  const openDeleteDialog = useCallback(
    (user: UserRecord) => {
      if (!canDeleteUsers) {
        return
      }

      if (!user.canDelete) {
        return
      }

      setRemovingUser(user)
      setPageError(null)
    },
    [canDeleteUsers]
  )

  const closeDeleteDialog = useCallback(() => {
    setRemovingUser(null)
  }, [])

  const openProfile = useCallback((user: UserRecord) => {
    setProfileUser(user)
  }, [])

  const closeProfile = useCallback(() => {
    setProfileUser(null)
  }, [])

  const requestStatusChange = useCallback(
    (ids: string[], nextStatus: UserStatus) => {
      if (!canEditUsers) return
      if (!ids.length) return

      const isSingle = ids.length === 1

      setConfirmAction({
        type: 'status',
        ids,
        nextStatus,
        title: nextStatus === 'enabled' ? 'Activate User Access' : 'Deactivate User Access',
        description: isSingle
          ? `This will ${nextStatus === 'enabled' ? 'activate' : 'deactivate'} the selected user account in the current company.`
          : `This will ${nextStatus === 'enabled' ? 'activate' : 'deactivate'} ${ids.length} selected user accounts in the current company.`,
        confirmLabel: nextStatus === 'enabled' ? 'Activate' : 'Deactivate',
        confirmColor: nextStatus === 'enabled' ? 'success' : 'error'
      })
    },
    [canEditUsers]
  )

  const handleConfirmAction = useCallback(async () => {
    if (!confirmAction) return

    const action = confirmAction
    const isSingle = action.ids.length === 1
    const subject = isSingle ? 'user' : 'users'
    const verb = action.nextStatus === 'enabled' ? 'activating' : 'deactivating'
    const successVerb = action.nextStatus === 'enabled' ? 'activated' : 'deactivated'

    setPageError(null)

    try {
      await toast.promise(setUsersStatus(action.ids, action.nextStatus), {
        pending: `${verb.charAt(0).toUpperCase()}${verb.slice(1)} ${subject}...`,
        success: `${isSingle ? 'User' : 'Users'} ${successVerb}`,
        error: {
          render({ data }: { data: unknown }) {
            return toErrorMessage(data, 'Could not update user status.')
          }
        }
      })

      setRowSelectionModel({ type: 'include', ids: new Set() })
      setConfirmAction(null)
    } catch {
      // Keep the dialog open so the user can retry after the toast error.
    }
  }, [confirmAction, setUsersStatus])

  const columns = useMemo(
    () =>
      getUserTableColumns({
        onView: openProfile,
        onEdit: openEditForm,
        onToggleStatus: user => requestStatusChange([user.id], user.status === 'enabled' ? 'disabled' : 'enabled'),
        onDelete: openDeleteDialog,
        canEditUsers,
        canDeleteUsers
      }),
    [canDeleteUsers, canEditUsers, openProfile, openEditForm, openDeleteDialog, requestStatusChange]
  )

  const handleSubmitUser = useCallback(
    async (values: UserFormSubmitValues) => {
      const payload = userFormSchema.parse(values)
      const isEditing = !!editingUser

      setPageError(null)

      try {
        if (isEditing && !canEditUsers) {
          throw new Error('You do not have permission to update users in this company.')
        }

        if (!isEditing && !canCreateUsers) {
          throw new Error('You do not have permission to create users in this company.')
        }

        if (!canReadRoles || !canReadOrgUnits) {
          throw new Error('Supporting role or org unit access is required to manage user assignments.')
        }

        await toast.promise(isEditing ? updateUser(editingUser.id, payload) : createUser(payload), {
          pending: isEditing ? 'Updating user...' : 'Creating user...',
          success: isEditing ? 'User updated' : 'User created',
          error: {
            render({ data }: { data: unknown }) {
              return toErrorMessage(data, isEditing ? 'Could not update the user.' : 'Could not create the user.')
            }
          }
        })

        closeForm()
      } catch (error) {
        setPageError(toErrorMessage(error, isEditing ? 'Could not update the user.' : 'Could not create the user.'))
      }
    },
    [canCreateUsers, canEditUsers, canReadOrgUnits, canReadRoles, editingUser, updateUser, createUser, closeForm]
  )

  const handleConfirmDelete = useCallback(async () => {
    if (!removingUser) return

    if (!canDeleteUsers) {
      setPageError('You do not have permission to remove users from this company.')

      return
    }

    try {
      await toast.promise(deleteUser(removingUser.id), {
        pending: 'Deleting user...',
        success: 'User removed from company',
        error: {
          render({ data }: { data: unknown }) {
            return data instanceof Error ? data.message : 'Could not delete the user.'
          }
        }
      })

      closeDeleteDialog()
    } catch {
      // Keep the dialog open so the user can retry.
    }
  }, [canDeleteUsers, closeDeleteDialog, deleteUser, removingUser])

  const bulkActionBar = canEditUsers && selectedIds.length > 0 && (
    <Alert
      severity='info'
      icon={false}
      sx={{ alignItems: 'center' }}
      action={
        <Stack direction='row' spacing={4}>
          <Button size='small' color='success' onClick={() => requestStatusChange(selectedIds, 'enabled')}>
            Activate
          </Button>
          <Button size='small' color='error' onClick={() => requestStatusChange(selectedIds, 'disabled')}>
            Deactivate
          </Button>
        </Stack>
      }
    >
      <Stack direction='row' spacing={2}>
        <Box component='i' className='bx bx-info-circle' sx={{ fontSize: 20, lineHeight: 1.2, mt: 0.2 }} />
        <Typography variant='body2'>{selectedIds.length} user(s) selected for bulk actions.</Typography>
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

      <CustomPageHeader
        title='User Management'
        description='Manage company users, role assignments, and org unit coverage across branches, departments, teams, and head office units.'
      >
        <Stack direction='row' spacing={4}>
          <CustomKPICard label='Total users' value={stats.total} loading={isBootstrapping} iconString='bx-user'>
            <Typography variant='h6' color='text.secondary'>
              Active Users
            </Typography>
          </CustomKPICard>
          <CustomKPICard
            label='Inactive users'
            value={stats.inactive}
            loading={isBootstrapping}
            iconString='bx-user'
            iconColor='error'
          />
        </Stack>
      </CustomPageHeader>

      <UserManagementFilters
        searchValue={search}
        onSearchChange={setSearch}
        statusValue={statusFilter}
        onStatusChange={setStatusFilter}
        accessValue={accessFilter}
        onAccessChange={setAccessFilter}
        roleValue={roleFilter}
        onRoleChange={setRoleFilter}
        primaryOrgUnitValue={primaryOrgUnitFilter}
        onPrimaryOrgUnitChange={setPrimaryOrgUnitFilter}
        roleOptions={roleOptions}
        orgUnitOptions={orgUnitOptions}
        onClearFilters={clearFilters}
        onCreateUser={openCreateForm}
        canCreateUser={
          canCreateUsers && canReadRoles && canReadOrgUnits && roleOptions.length > 0 && orgUnitOptions.length > 0
        }
      />

      {!canManageUsers ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          You have view-only access in this company. User changes appear only when your role grants create, edit, or
          delete access.
        </Alert>
      ) : null}

      {canManageUsers && !canReadRoles ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Role reference data is not available for this access level, so creating or editing user role assignments is
          disabled.
        </Alert>
      ) : null}

      {canManageUsers && !canReadOrgUnits ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Company-structure reference data is not available for this access level, so creating or editing user org unit
          assignments is disabled.
        </Alert>
      ) : null}

      {error ? (
        <Alert severity='error' sx={{ mb: 3 }}>
          {error instanceof Error ? error.message : 'Could not load user management data.'}
        </Alert>
      ) : null}

      {pageError ? (
        <Alert severity='error' sx={{ mb: 3 }}>
          {pageError}
        </Alert>
      ) : null}

      {canReadRoles && roleOptions.length === 0 ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Create at least one role in the current company before assigning user access.
        </Alert>
      ) : null}

      {canReadOrgUnits && orgUnitOptions.length === 0 ? (
        <Alert severity='info' sx={{ mb: 3 }}>
          Set up at least one company org unit before adding or reassigning users.
        </Alert>
      ) : null}

      {isBootstrapping ? (
        <UserManagementSkeleton />
      ) : users.length === 0 ? (
        <UserManagementStateCard
          iconClassName='bx bx-user-plus'
          title='No users yet'
          description='Create your first company user to start managing roles and org unit coverage.'
          actionLabel={
            canCreateUsers && canReadRoles && canReadOrgUnits && roleOptions.length > 0 && orgUnitOptions.length > 0
              ? 'Create User'
              : undefined
          }
          onAction={
            canCreateUsers && canReadRoles && canReadOrgUnits && roleOptions.length > 0 && orgUnitOptions.length > 0
              ? openCreateForm
              : undefined
          }
        />
      ) : filteredUsers.length === 0 ? (
        <UserManagementStateCard
          iconClassName='bx bx-search-alt'
          title='No matching users found'
          description='No users matched your current search and filter criteria. Clear filters to see all users.'
          actionLabel='Reset Filters'
          onAction={clearFilters}
        />
      ) : (
        <Paper>
          <Box sx={{ p: 5, pb: 0 }}>{bulkActionBar}</Box>
          <UserManagementTable
            rows={filteredUsers}
            columns={columns}
            loading={false}
            sortModel={sortModel}
            onSortModelChange={setSortModel}
            isRowSelectable={({ row }) => canEditUsers && !row.isOwner && row.canEdit}
            enableRowSelection={canEditUsers}
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

      <UserFormDrawer
        open={isFormOpen}
        mode={editingUser ? 'edit' : 'create'}
        defaultUser={editingUser}
        roleOptions={roleOptions}
        orgUnitOptions={orgUnitOptions}
        isSubmitting={isSaving}
        submissionError={pageError}
        onClose={closeForm}
        onSubmit={handleSubmitUser}
      />

      <CustomDialog
        open={!!removingUser}
        onClose={closeDeleteDialog}
        title='Delete User'
        description={
          removingUser
            ? `Delete ${removingUser.fullName} from the current company? This removes the user from the company directory and all org unit assignments.`
            : ''
        }
        closeAfterTransition
        disableEscapeKeyDown={false}
        actions={
          <>
            <Button variant='outlined' color='secondary' onClick={closeDeleteDialog} disabled={isSaving}>
              Cancel
            </Button>
            <Button
              variant='contained'
              color='error'
              onClick={handleConfirmDelete}
              disabled={!removingUser || isSaving}
            >
              Delete User
            </Button>
          </>
        }
      >
        {null}
      </CustomDialog>

      <UserProfilePanel open={!!profileUser} user={profileUser} onClose={closeProfile} />

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
            <Button variant='contained' color={confirmAction?.confirmColor ?? 'primary'} onClick={handleConfirmAction}>
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

export default UserManagementPage
