import { useEffect, useMemo } from 'react'

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormHelperText from '@mui/material/FormHelperText'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import Switch from '@mui/material/Switch'
import Typography from '@mui/material/Typography'

import { Controller, type SubmitHandler, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import CustomDrawer from '@components/app/CustomDrawer'
import CustomTextField from '@core/components/mui/TextField'

import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'
import {
  type UserFormSubmitValues,
  type UserFormValues,
  userFormSchema
} from '@/features/(protected)/users/user-management/model/user-management.schema'
import {
  getUserRoleScopeLabel,
  type UserOrgUnitOption,
  type UserRecord,
  type UserRoleOption
} from '@/features/(protected)/users/user-management/model/user-management.types'

type UserFormDrawerProps = {
  open: boolean
  mode: 'create' | 'edit'
  defaultUser: UserRecord | null
  roleOptions: UserRoleOption[]
  orgUnitOptions: UserOrgUnitOption[]
  isSubmitting?: boolean
  submissionError?: string | null
  onClose: () => void
  onSubmit: (values: UserFormSubmitValues) => void | Promise<void>
}

const baseDefaults: UserFormValues = {
  fullName: '',
  email: '',
  password: '',
  roleIds: [],
  primaryOrgUnitId: '',
  additionalOrgUnitIds: [],
  roleAssignmentScope: 'primary_org_unit',
  scopeOrgUnitIds: [],
  status: 'enabled'
}

const EMPTY_IDS: string[] = []

function renderSelectedChipValues(selected: string[], items: Array<{ id: string; label: string }>) {
  return (
    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.75 }}>
      {selected.map(itemId => {
        const option = items.find(item => item.id === itemId)

        return <Chip key={itemId} size='small' label={option?.label ?? itemId} />
      })}
    </Box>
  )
}

function UserFormDrawer({
  open,
  mode,
  defaultUser,
  roleOptions,
  orgUnitOptions,
  isSubmitting,
  submissionError,
  onClose,
  onSubmit
}: UserFormDrawerProps) {
  const isOwnerEdit = mode === 'edit' && !!defaultUser?.isOwner

  const {
    control,
    register,
    handleSubmit,
    reset,
    setValue,
    formState: { errors }
  } = useForm<UserFormValues>({
    resolver: zodResolver(userFormSchema),
    defaultValues: baseDefaults
  })

  const primaryOrgUnitId = useWatch({ control, name: 'primaryOrgUnitId' }) ?? ''
  const watchedAdditionalOrgUnitIds = useWatch({ control, name: 'additionalOrgUnitIds' })
  const additionalOrgUnitIds = watchedAdditionalOrgUnitIds ?? EMPTY_IDS
  const roleAssignmentScope = useWatch({ control, name: 'roleAssignmentScope' }) ?? 'primary_org_unit'
  const watchedScopeOrgUnitIds = useWatch({ control, name: 'scopeOrgUnitIds' })
  const scopeOrgUnitIds = watchedScopeOrgUnitIds ?? EMPTY_IDS

  const assignedScopeOptions = useMemo(() => {
    const assignedIds = new Set([primaryOrgUnitId, ...additionalOrgUnitIds].filter(Boolean))

    return orgUnitOptions.filter(orgUnit => assignedIds.has(orgUnit.id))
  }, [additionalOrgUnitIds, orgUnitOptions, primaryOrgUnitId])

  useEffect(() => {
    if (!open) {
      reset(baseDefaults)

      return
    }

    if (!defaultUser) {
      reset(baseDefaults)

      return
    }

    reset({
      fullName: defaultUser.fullName,
      email: defaultUser.email,
      password: '',
      roleIds: defaultUser.roleIds.slice(0, 1),
      primaryOrgUnitId: defaultUser.primaryOrgUnit?.id ?? '',
      additionalOrgUnitIds: defaultUser.additionalOrgUnits.map(orgUnit => orgUnit.id),
      roleAssignmentScope: defaultUser.roleAssignmentScope,
      scopeOrgUnitIds:
        defaultUser.roleAssignmentScope === 'primary_org_unit'
          ? defaultUser.primaryOrgUnit
            ? [defaultUser.primaryOrgUnit.id]
            : []
          : defaultUser.scopeOrgUnits.map(orgUnit => orgUnit.id),
      status: defaultUser.status
    })
  }, [defaultUser, open, reset])

  useEffect(() => {
    if (!primaryOrgUnitId) {
      return
    }

    if (additionalOrgUnitIds.includes(primaryOrgUnitId)) {
      setValue(
        'additionalOrgUnitIds',
        additionalOrgUnitIds.filter(orgUnitId => orgUnitId !== primaryOrgUnitId),
        { shouldDirty: true, shouldValidate: true }
      )
    }
  }, [additionalOrgUnitIds, primaryOrgUnitId, setValue])

  useEffect(() => {
    const assignedIds = new Set([primaryOrgUnitId, ...additionalOrgUnitIds].filter(Boolean))

    if (roleAssignmentScope === 'company') {
      if (scopeOrgUnitIds.length > 0) {
        setValue('scopeOrgUnitIds', [], { shouldDirty: true, shouldValidate: true })
      }

      return
    }

    if (roleAssignmentScope === 'primary_org_unit') {
      const nextValue = primaryOrgUnitId ? [primaryOrgUnitId] : []

      if (JSON.stringify(scopeOrgUnitIds) !== JSON.stringify(nextValue)) {
        setValue('scopeOrgUnitIds', nextValue, { shouldDirty: true, shouldValidate: true })
      }

      return
    }

    const filteredScopeIds = scopeOrgUnitIds.filter(orgUnitId => assignedIds.has(orgUnitId))

    if (filteredScopeIds.length !== scopeOrgUnitIds.length) {
      setValue('scopeOrgUnitIds', filteredScopeIds, { shouldDirty: true, shouldValidate: true })

      return
    }

    if (filteredScopeIds.length === 0 && primaryOrgUnitId) {
      setValue('scopeOrgUnitIds', [primaryOrgUnitId], { shouldDirty: true, shouldValidate: true })
    }
  }, [additionalOrgUnitIds, primaryOrgUnitId, roleAssignmentScope, scopeOrgUnitIds, setValue])

  const submit: SubmitHandler<UserFormValues> = values => {
    onSubmit(userFormSchema.parse(values))
  }

  return (
    <CustomDrawer
      open={open}
      onClose={onClose}
      width={520}
      title={mode === 'create' ? 'Create User' : 'Edit User'}
      subtitle={
        mode === 'create'
          ? 'Add a company user and define their org unit coverage.'
          : 'Update user profile, roles, and org unit coverage.'
      }
    >
      <Stack component='form' spacing={4} sx={{ p: 5 }} onSubmit={handleSubmit(submit)}>
        {submissionError ? (
          <Alert severity='error' variant='outlined'>
            {submissionError}
          </Alert>
        ) : null}

        <Typography variant='h5'>Profile Details</Typography>

        <CustomTextField
          label='Full Name'
          fullWidth
          disabled={isSubmitting}
          {...register('fullName')}
          error={!!errors.fullName}
          helperText={errors.fullName?.message}
        />

        <CustomTextField
          label='Email'
          fullWidth
          type='email'
          disabled={isSubmitting}
          {...register('email')}
          error={!!errors.email}
          helperText={errors.email?.message}
        />

        {mode === 'create' ? (
          <CustomTextField
            label='Password'
            type='password'
            fullWidth
            disabled={isSubmitting}
            {...register('password')}
            error={!!errors.password}
            helperText={
              errors.password?.message ?? 'Required for a brand-new user. Leave blank if the email already exists.'
            }
          />
        ) : null}

        <Typography variant='h5'>Company Structure</Typography>

        <Controller
          name='primaryOrgUnitId'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.primaryOrgUnitId}>
              <InputLabel id='user-primary-org-unit-label'>Primary Org Unit</InputLabel>
              <Select
                {...field}
                labelId='user-primary-org-unit-label'
                label='Primary Org Unit'
                disabled={isSubmitting}
                value={field.value ?? ''}
                onChange={event => field.onChange(event.target.value)}
              >
                {orgUnitOptions.map(orgUnit => (
                  <MenuItem key={orgUnit.id} value={orgUnit.id}>
                    {orgUnit.label}
                  </MenuItem>
                ))}
              </Select>
              <FormHelperText>
                {errors.primaryOrgUnitId?.message ??
                  "Sets the user's main branch, department, team, or head office unit."}
              </FormHelperText>
            </FormControl>
          )}
        />

        <Controller
          name='additionalOrgUnitIds'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.additionalOrgUnitIds}>
              <InputLabel id='user-additional-org-units-label'>Additional Org Units</InputLabel>
              <Select
                {...field}
                multiple
                labelId='user-additional-org-units-label'
                label='Additional Org Units'
                disabled={isSubmitting}
                value={field.value ?? []}
                onChange={event => field.onChange(event.target.value as string[])}
                renderValue={selected => renderSelectedChipValues(selected as string[], orgUnitOptions)}
              >
                {orgUnitOptions.map(orgUnit => (
                  <MenuItem key={orgUnit.id} value={orgUnit.id}>
                    {orgUnit.label}
                  </MenuItem>
                ))}
              </Select>
              <FormHelperText>
                {errors.additionalOrgUnitIds?.message ?? 'Optional. Add any other org units this user works with.'}
              </FormHelperText>
            </FormControl>
          )}
        />

        <Typography variant='h5'>Roles and Scope</Typography>

        <Controller
          name='roleIds'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.roleIds}>
              <InputLabel id='user-role-label'>Role</InputLabel>
              <Select
                labelId='user-role-label'
                label='Role'
                disabled={isSubmitting}
                value={field.value?.[0] ?? ''}
                onChange={event => field.onChange(event.target.value ? [event.target.value] : [])}
              >
                {roleOptions.map(role => (
                  <MenuItem key={role.id} value={role.id}>
                    {role.label}
                  </MenuItem>
                ))}
              </Select>
              <FormHelperText>{errors.roleIds?.message ?? 'Assign one company role for this user.'}</FormHelperText>
            </FormControl>
          )}
        />

        <Controller
          name='roleAssignmentScope'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.roleAssignmentScope}>
              <InputLabel id='user-role-scope-label'>Operational Scope</InputLabel>
              <Select
                {...field}
                labelId='user-role-scope-label'
                label='Operational Scope'
                disabled={isSubmitting}
                value={field.value ?? 'primary_org_unit'}
                onChange={event => field.onChange(event.target.value)}
              >
                <MenuItem value='company'>{getUserRoleScopeLabel('company')}</MenuItem>
                <MenuItem value='primary_org_unit'>{getUserRoleScopeLabel('primary_org_unit')}</MenuItem>
                <MenuItem value='selected_org_units'>{getUserRoleScopeLabel('selected_org_units')}</MenuItem>
              </Select>
              <FormHelperText>
                {roleAssignmentScope === 'company'
                  ? 'Use this when the user needs the same role coverage across the whole company.'
                  : roleAssignmentScope === 'primary_org_unit'
                    ? 'Use this when the user operates mainly from their primary org unit.'
                    : 'Use this when the user supports only specific org units.'}
              </FormHelperText>
            </FormControl>
          )}
        />

        {roleAssignmentScope === 'selected_org_units' ? (
          <Controller
            name='scopeOrgUnitIds'
            control={control}
            render={({ field }) => (
              <FormControl fullWidth error={!!errors.scopeOrgUnitIds}>
                <InputLabel id='user-scope-org-units-label'>Scoped Org Units</InputLabel>
                <Select
                  {...field}
                  multiple
                  labelId='user-scope-org-units-label'
                  label='Scoped Org Units'
                  disabled={isSubmitting || assignedScopeOptions.length === 0}
                  value={field.value ?? []}
                  onChange={event => field.onChange(event.target.value as string[])}
                  renderValue={selected => renderSelectedChipValues(selected as string[], assignedScopeOptions)}
                >
                  {assignedScopeOptions.map(orgUnit => (
                    <MenuItem key={orgUnit.id} value={orgUnit.id}>
                      {orgUnit.label}
                    </MenuItem>
                  ))}
                </Select>
                <FormHelperText>
                  {errors.scopeOrgUnitIds?.message ??
                    'Choose which assigned org units the selected roles should cover.'}
                </FormHelperText>
              </FormControl>
            )}
          />
        ) : null}

        <Alert severity='info' variant='outlined' icon={false}>
          Roles are defined at the company level. Org unit coverage controls where this user operates across head
          office, branches, departments, and teams.
        </Alert>

        {primaryOrgUnitId ? (
          <Box>
            <Typography variant='caption' color='text.secondary' sx={{ display: 'block', mb: 1.2 }}>
              Current Assignment Preview
            </Typography>
            <Stack direction='row' spacing={1} useFlexGap flexWrap='wrap'>
              {orgUnitOptions
                .filter(orgUnit => [primaryOrgUnitId, ...additionalOrgUnitIds].includes(orgUnit.id))
                .map(orgUnit => (
                  <Chip
                    key={orgUnit.id}
                    size='small'
                    label={`${orgUnit.name} (${getOrgUnitTypeLabel(orgUnit.type)})`}
                    color={orgUnit.id === primaryOrgUnitId ? 'primary' : 'default'}
                    variant={orgUnit.id === primaryOrgUnitId ? 'tonal' : 'outlined'}
                  />
                ))}
            </Stack>
          </Box>
        ) : null}

        <Typography variant='h5'>Access Controls</Typography>

        <Controller
          name='status'
          control={control}
          render={({ field }) => (
            <FormControlLabel
              disabled={isSubmitting || isOwnerEdit}
              control={
                <Switch
                  checked={field.value === 'enabled'}
                  disabled={isOwnerEdit}
                  onChange={(_, checked) => field.onChange(checked ? 'enabled' : 'disabled')}
                />
              }
              label={field.value === 'enabled' ? 'User Active' : 'User Inactive'}
            />
          )}
        />

        {isOwnerEdit ? (
          <Alert severity='info' variant='outlined'>
            The company owner must remain active and cannot be deactivated from this form.
          </Alert>
        ) : null}

        <Stack direction='row' spacing={2} justifyContent='flex-end' sx={{ pt: 2 }}>
          <Button variant='outlined' color='secondary' onClick={onClose} disabled={isSubmitting}>
            Discard
          </Button>
          <Button variant='contained' type='submit' loading={isSubmitting}>
            {mode === 'create' ? 'Create User' : 'Save Changes'}
          </Button>
        </Stack>
      </Stack>
    </CustomDrawer>
  )
}

export default UserFormDrawer
