import { useEffect, useMemo, useRef } from 'react'

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import Chip from '@mui/material/Chip'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormHelperText from '@mui/material/FormHelperText'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Paper from '@mui/material/Paper'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import Switch from '@mui/material/Switch'
import ToggleButton from '@mui/material/ToggleButton'
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup'
import Typography from '@mui/material/Typography'

import { Controller, type SubmitHandler, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import CustomDrawer from '@components/app/CustomDrawer'
import CustomTextField from '@core/components/mui/TextField'

import {
  type RoleFormSubmitValues,
  type RoleFormValues,
  roleFormSchema
} from '@/features/(protected)/users/roles/model/roles.schema'
import type { RoleOrgUnitOption, RoleRecord } from '@/features/(protected)/users/roles/model/roles.types'
import {
  createEmptyRoleModulePermissions,
  enforceScopePermissions,
  getEditableRoleActionKeysByAccessLevel,
  getRoleAccessPreset,
  ROLE_PERMISSION_MODULES
} from '@/features/(protected)/users/roles/model/role-access-control'

type RoleFormDrawerProps = {
  open: boolean
  mode: 'create' | 'edit'
  defaultRole: RoleRecord | null
  orgUnitOptions: RoleOrgUnitOption[]
  isSubmitting?: boolean
  onClose: () => void
  onSubmit: (values: RoleFormSubmitValues) => void | Promise<void>
}

function cloneModulePermissions(modulePermissions: RoleFormValues['modulePermissions']) {
  const cloned = createEmptyRoleModulePermissions()

  ROLE_PERMISSION_MODULES.forEach(module => {
    cloned[module.key] = [...(modulePermissions[module.key] ?? [])]
  })

  return cloned
}

function normalizeRoleKey(value: string) {
  return value.trim().toUpperCase()
}

const basePreset = getRoleAccessPreset('', 'read')

const baseDefaults: RoleFormValues = {
  name: '',
  key: '',
  orgUnitId: '',
  status: 'enabled',
  accessLevel: 'read',
  permissionScope: basePreset.permissionScope,
  modulePermissions: cloneModulePermissions(basePreset.modulePermissions),
  permissions: [...basePreset.permissions],
  description: ''
}

function RoleFormDrawer({
  open,
  mode,
  defaultRole,
  orgUnitOptions,
  isSubmitting,
  onClose,
  onSubmit
}: RoleFormDrawerProps) {
  const isAdminRoleEdit = mode === 'edit' && !!defaultRole?.isAdminRole

  const {
    control,
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors }
  } = useForm<RoleFormValues>({
    resolver: zodResolver(roleFormSchema),
    defaultValues: baseDefaults
  })

  const previousRoleKeyRef = useRef<string | null>(null)

  const accessLevel = watch('accessLevel')
  const permissionScope = watch('permissionScope')
  const roleKey = watch('key')
  const modulePermissions = watch('modulePermissions')

  const normalizedModulePermissions = useMemo(
    () => cloneModulePermissions(modulePermissions ?? createEmptyRoleModulePermissions()),
    [modulePermissions]
  )

  const templateInfo = useMemo(() => getRoleAccessPreset(roleKey, accessLevel), [roleKey, accessLevel])

  const totalAvailableActionCount = useMemo(
    () =>
      ROLE_PERMISSION_MODULES.reduce((total, module) => {
        return total + getEditableRoleActionKeysByAccessLevel(module.key, accessLevel).length
      }, 0),
    [accessLevel]
  )

  const selectedActionCount = useMemo(
    () =>
      ROLE_PERMISSION_MODULES.reduce((total, module) => {
        const editableActions = new Set(getEditableRoleActionKeysByAccessLevel(module.key, accessLevel))

        return (
          total + (normalizedModulePermissions[module.key] ?? []).filter(action => editableActions.has(action)).length
        )
      }, 0),
    [accessLevel, normalizedModulePermissions]
  )

  const modulePermissionsError =
    typeof errors.modulePermissions?.message === 'string' ? errors.modulePermissions.message : null

  const showsLegacyOwnScopeNotice = mode === 'edit' && defaultRole?.rawPermissionScope === 'own'

  const applyAccessState = (
    nextScope: RoleFormValues['permissionScope'],
    nextModulePermissions: RoleFormValues['modulePermissions'],
    nextAccessLevel: RoleFormValues['accessLevel']
  ) => {
    const normalized = enforceScopePermissions(
      nextScope,
      cloneModulePermissions(nextModulePermissions),
      nextAccessLevel
    )

    setValue('modulePermissions', normalized.modulePermissions, { shouldDirty: true, shouldValidate: true })
    setValue('permissions', normalized.permissions, { shouldDirty: true, shouldValidate: true })
  }

  useEffect(() => {
    if (!open) {
      previousRoleKeyRef.current = null
      reset(baseDefaults)

      return
    }

    if (!defaultRole) {
      previousRoleKeyRef.current = normalizeRoleKey(baseDefaults.key)
      reset(baseDefaults)

      return
    }

    const fallbackPreset = getRoleAccessPreset(defaultRole.key, defaultRole.accessLevel)

    previousRoleKeyRef.current = normalizeRoleKey(defaultRole.key)

    reset({
      name: defaultRole.name,
      key: defaultRole.key,
      orgUnitId: defaultRole.orgUnit?.id ?? '',
      status: defaultRole.status,
      accessLevel: defaultRole.accessLevel,
      permissionScope: defaultRole.permissionScope ?? fallbackPreset.permissionScope,
      modulePermissions: cloneModulePermissions(defaultRole.modulePermissions ?? fallbackPreset.modulePermissions),
      permissions: defaultRole.permissions.length ? [...defaultRole.permissions] : [...fallbackPreset.permissions],
      description: defaultRole.description
    })
  }, [defaultRole, open, reset])

  useEffect(() => {
    if (!open || mode !== 'create') return

    const normalizedRoleKey = normalizeRoleKey(roleKey)

    if (previousRoleKeyRef.current === null) {
      previousRoleKeyRef.current = normalizedRoleKey

      return
    }

    if (normalizedRoleKey === previousRoleKeyRef.current) return

    previousRoleKeyRef.current = normalizedRoleKey

    const preset = getRoleAccessPreset(normalizedRoleKey, accessLevel)

    setValue('permissionScope', preset.permissionScope, { shouldDirty: true, shouldValidate: true })
    setValue('modulePermissions', cloneModulePermissions(preset.modulePermissions), {
      shouldDirty: true,
      shouldValidate: true
    })
    setValue('permissions', preset.permissions, { shouldDirty: true, shouldValidate: true })
  }, [accessLevel, mode, open, roleKey, setValue])

  const submit: SubmitHandler<RoleFormValues> = values => {
    const normalized = enforceScopePermissions(values.permissionScope, values.modulePermissions, values.accessLevel)

    onSubmit(
      roleFormSchema.parse({
        ...values,
        modulePermissions: normalized.modulePermissions,
        permissions: normalized.permissions
      })
    )
  }

  return (
    <CustomDrawer
      open={open}
      onClose={onClose}
      width={760}
      title={mode === 'create' ? 'Create Role' : 'Role Settings'}
      subtitle={mode === 'create' ? 'Add a new role profile for ERP access' : 'Update role information and controls'}
    >
      <Stack component='form' spacing={4} sx={{ p: 5 }} onSubmit={handleSubmit(submit)}>
        <Typography variant='h5'>Role Details</Typography>

        <CustomTextField
          label='Role Name'
          fullWidth
          disabled={isSubmitting}
          {...register('name')}
          error={!!errors.name}
          helperText={errors.name?.message}
        />

        <CustomTextField
          label='Role Key'
          fullWidth
          disabled={isSubmitting || isAdminRoleEdit}
          {...register('key')}
          error={!!errors.key}
          helperText={
            errors.key?.message ??
            (isAdminRoleEdit
              ? 'The admin role key is fixed and cannot be changed.'
              : 'Use uppercase letters, numbers, underscore only')
          }
        />

        <Controller
          name='orgUnitId'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.orgUnitId}>
              <InputLabel id='role-org-unit-label'>Org Unit Context</InputLabel>
              <Select {...field} labelId='role-org-unit-label' label='Org Unit Context' disabled={isSubmitting}>
                <MenuItem value=''>Company-wide</MenuItem>
                {orgUnitOptions.map(orgUnit => (
                  <MenuItem key={orgUnit.id} value={orgUnit.id}>
                    {orgUnit.label}
                  </MenuItem>
                ))}
              </Select>
              <FormHelperText>
                {errors.orgUnitId?.message ??
                  'Optional. Link this role to a branch, department, team, or head office context.'}
              </FormHelperText>
            </FormControl>
          )}
        />

        <CustomTextField
          label='Description'
          fullWidth
          multiline
          minRows={3}
          disabled={isSubmitting}
          {...register('description')}
          error={!!errors.description}
          helperText={errors.description?.message}
        />

        <Typography variant='h5'>Access Controls</Typography>

        <Controller
          name='status'
          control={control}
          render={({ field }) => (
            <FormControlLabel
              disabled={isSubmitting || isAdminRoleEdit}
              control={
                <Switch
                  checked={field.value === 'enabled'}
                  disabled={isAdminRoleEdit}
                  onChange={(_, checked) => field.onChange(checked ? 'enabled' : 'disabled')}
                />
              }
              label={field.value === 'enabled' ? 'Role Active' : 'Role Inactive'}
            />
          )}
        />

        {isAdminRoleEdit ? (
          <Alert severity='info' variant='outlined'>
            The admin role can only be edited by the company owner, and it must remain active.
          </Alert>
        ) : null}

        {showsLegacyOwnScopeNotice ? (
          <Alert severity='warning' variant='outlined'>
            This role uses the legacy Own scope. The current form will preserve that scope on save, but full own-scope
            editing is not available yet.
          </Alert>
        ) : null}

        <Controller
          name='accessLevel'
          control={control}
          render={({ field }) => (
            <FormControl error={!!errors.accessLevel}>
              <Typography variant='subtitle2' color='text.secondary'>
                Access Level
              </Typography>
              <Stack direction='row' spacing={2}>
                <FormControlLabel
                  disabled={isSubmitting}
                  control={
                    <Checkbox
                      checked={field.value === 'read'}
                      onChange={(_, checked) => {
                        if (!checked) return

                        field.onChange('read')
                        applyAccessState(permissionScope, normalizedModulePermissions, 'read')
                      }}
                    />
                  }
                  label='Read'
                />
                <FormControlLabel
                  disabled={isSubmitting}
                  control={
                    <Checkbox
                      checked={field.value === 'write'}
                      onChange={(_, checked) => {
                        if (!checked) return

                        field.onChange('write')
                        applyAccessState(permissionScope, normalizedModulePermissions, 'write')
                      }}
                    />
                  }
                  label='Write'
                />
              </Stack>
              <FormHelperText>{errors.accessLevel?.message}</FormHelperText>
            </FormControl>
          )}
        />

        <Paper variant='outlined' sx={{ p: 4 }}>
          <Stack spacing={3}>
            <Stack
              direction={{ xs: 'column', sm: 'row' }}
              spacing={1.5}
              alignItems={{ xs: 'flex-start', sm: 'center' }}
            >
              <Typography variant='h6'>Permission Control Level</Typography>
              <Chip
                size='small'
                color={templateInfo.isKnownTemplate ? 'primary' : 'default'}
                variant={templateInfo.isKnownTemplate ? 'filled' : 'outlined'}
                label={templateInfo.templateLabel}
              />
            </Stack>

            <Controller
              name='permissionScope'
              control={control}
              render={({ field }) => (
                <Stack spacing={1}>
                  <Typography variant='subtitle2' color='text.secondary'>
                    Scope
                  </Typography>
                  <ToggleButtonGroup
                    value={field.value}
                    exclusive
                    color='primary'
                    onChange={(_, value: RoleFormValues['permissionScope'] | null) => {
                      if (!value) return

                      field.onChange(value)
                      applyAccessState(value, normalizedModulePermissions, accessLevel)
                    }}
                  >
                    <ToggleButton value='all'>All Pages</ToggleButton>
                    <ToggleButton value='page'>Per Page</ToggleButton>
                  </ToggleButtonGroup>
                </Stack>
              )}
            />

            <Alert severity={permissionScope === 'all' ? 'success' : 'info'} icon={false} variant='standard'>
              <Stack direction='row' spacing={2} alignItems='center'>
                <Box component='i' className='bx-info-circle' sx={{ fontSize: 20 }} />

                <Stack direction='row' spacing={2} alignItems='center'>
                  {permissionScope === 'all'
                    ? 'All enforced module permissions are active based on selected access level.'
                    : 'Customize enforced module permissions per page.'}
                </Stack>
              </Stack>
            </Alert>

            <Alert severity='info' variant='outlined'>
              Assign access to accounting, journals, periods, and role administration for this company.
            </Alert>

            <Stack direction='row' justifyContent='space-between' alignItems='center'>
              <Typography variant='body2' color='text.secondary'>
                What This Role Can Do
              </Typography>
              <Typography variant='body2' color='primary.main'>
                {selectedActionCount} of {totalAvailableActionCount} permissions selected
              </Typography>
            </Stack>

            <Stack spacing={2}>
              {ROLE_PERMISSION_MODULES.map(module => {
                const editableModuleActions = getEditableRoleActionKeysByAccessLevel(module.key, accessLevel)
                const editableActionSet = new Set(editableModuleActions)
                const visibleModuleActions = module.actions.filter(action => editableActionSet.has(action.key))

                const selectedEditableActions = (normalizedModulePermissions[module.key] ?? []).filter(action =>
                  editableActionSet.has(action)
                )

                const isModuleFullySelected =
                  editableModuleActions.length > 0 && selectedEditableActions.length === editableModuleActions.length

                const isModulePartiallySelected =
                  selectedEditableActions.length > 0 && selectedEditableActions.length < editableModuleActions.length

                return (
                  <Paper key={module.key} variant='outlined' sx={{ overflow: 'hidden' }}>
                    <Box
                      sx={{ px: 3, py: 2, borderBottom: '1px solid', borderColor: 'divider', bgcolor: 'action.hover' }}
                    >
                      <Stack direction='row' justifyContent='space-between' alignItems='center' spacing={2}>
                        <FormControlLabel
                          sx={{ m: 0 }}
                          disabled={isSubmitting || permissionScope === 'all' || editableModuleActions.length === 0}
                          control={
                            <Checkbox
                              checked={isModuleFullySelected}
                              indeterminate={isModulePartiallySelected}
                              onChange={(_, checked) => {
                                const nextModules = cloneModulePermissions(normalizedModulePermissions)

                                const preservedPendingActions = (nextModules[module.key] ?? []).filter(
                                  action => !editableActionSet.has(action)
                                )

                                nextModules[module.key] = checked
                                  ? [...preservedPendingActions, ...editableModuleActions]
                                  : preservedPendingActions

                                applyAccessState(permissionScope, nextModules, accessLevel)
                              }}
                            />
                          }
                          label={
                            <Box>
                              <Typography variant='subtitle1' sx={{ fontWeight: 600 }}>
                                {module.label}
                              </Typography>
                              <Typography variant='caption' color='text.secondary'>
                                {module.description}
                              </Typography>
                            </Box>
                          }
                        />
                        <Stack direction='row' spacing={1} alignItems='center'>
                          <Chip
                            size='small'
                            label={
                              editableModuleActions.length > 0
                                ? `${selectedEditableActions.length}/${editableModuleActions.length} selected`
                                : 'No available actions'
                            }
                            variant='outlined'
                            color={editableModuleActions.length > 0 ? 'default' : 'warning'}
                          />
                        </Stack>
                      </Stack>
                    </Box>

                    <Stack spacing={1.2} sx={{ p: 3 }}>
                      {visibleModuleActions.map(action => {
                        const isChecked = selectedEditableActions.includes(action.key)

                        return (
                          <FormControlLabel
                            key={`${module.key}-${action.key}-${action.label}`}
                            sx={{
                              alignItems: 'flex-start',
                              m: 0
                            }}
                            disabled={isSubmitting || permissionScope === 'all'}
                            control={
                              <Checkbox
                                checked={isChecked}
                                onChange={(_, checked) => {
                                  const nextModules = cloneModulePermissions(normalizedModulePermissions)
                                  const current = nextModules[module.key]

                                  nextModules[module.key] = checked
                                    ? current.includes(action.key)
                                      ? current
                                      : [...current, action.key]
                                    : current.filter(item => item !== action.key)

                                  applyAccessState(permissionScope, nextModules, accessLevel)
                                }}
                              />
                            }
                            label={
                              <Box sx={{ pt: 0.5 }}>
                                <Typography variant='body2' sx={{ fontWeight: 500 }}>
                                  {action.label}
                                </Typography>
                                <Typography variant='caption' color='text.secondary'>
                                  {action.description}
                                </Typography>
                              </Box>
                            }
                          />
                        )
                      })}
                    </Stack>
                  </Paper>
                )
              })}
            </Stack>

            {modulePermissionsError ? <FormHelperText error>{modulePermissionsError}</FormHelperText> : null}
            {errors.permissions?.message ? <FormHelperText error>{errors.permissions.message}</FormHelperText> : null}
          </Stack>
        </Paper>

        <Stack direction='row' spacing={2} justifyContent='flex-end' sx={{ pt: 2 }}>
          <Button variant='outlined' color='secondary' onClick={onClose} disabled={isSubmitting}>
            Discard
          </Button>
          <Button variant='contained' type='submit' loading={isSubmitting}>
            {mode === 'create' ? 'Create Role' : 'Save Changes'}
          </Button>
        </Stack>
      </Stack>
    </CustomDrawer>
  )
}

export default RoleFormDrawer
