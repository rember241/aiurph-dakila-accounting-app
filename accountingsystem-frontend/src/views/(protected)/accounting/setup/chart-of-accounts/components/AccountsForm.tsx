import React, { useEffect } from 'react'

import { Box, FormControl, FormHelperText, InputLabel, Select, Stack } from '@mui/material'
import Typography from '@mui/material/Typography'

import MenuItem from '@mui/material/MenuItem'

import Button from '@mui/material/Button'

import { Controller, type SubmitHandler, useForm, useWatch } from 'react-hook-form'

import { zodResolver } from '@hookform/resolvers/zod'

import Switch from '@mui/material/Switch'

import FormControlLabel from '@mui/material/FormControlLabel'

import CustomTextField from '@core/components/mui/TextField'

import {
  type AccountCreateFormValues,
  accountCreateSchema,
  type AccountCreateSubmitValues
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/account.schema'
import {
  useAccountTypesByCoreTypeQuery,
  useDetailTypesByAccountTypeQuery
} from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'

type AccountsFormProps = {
  actionType: 'create' | 'update'
  onSubmit: (values: AccountCreateSubmitValues) => void | Promise<void>
  onDiscard: () => void
  isSubmitting?: boolean
  defaultValues?: Partial<AccountCreateFormValues>
  contraOptions?: { id: string; code: string; name: string }[]
  parentOptions?: {
    id: string
    code: string
    name: string
    type: string
    level: number
    is_posting: boolean
    has_children?: boolean
  }[]
  isOpen: boolean
}

const baseDefaults: AccountCreateFormValues = {
  name: '',
  type: 'asset',
  parent_id: null,
  is_posting: true,
  status: 'active',
  contra_account_id: null,
  description: null,
  detail_type_id: '',
  account_type_id: ''
}

function AccountsForm({
  actionType,
  onSubmit,
  onDiscard,
  isSubmitting,
  defaultValues,
  contraOptions,
  parentOptions,
  isOpen
}: AccountsFormProps) {
  // Form Hook
  const {
    control,
    register,
    handleSubmit,
    formState: { errors, isDirty },
    reset,
    setValue
  } = useForm<AccountCreateFormValues>({
    resolver: zodResolver(accountCreateSchema),
    defaultValues: baseDefaults
  })

  // Form Dropdown
  const coreType = useWatch({ control, name: 'type' })
  const accountTypeId = useWatch({ control, name: 'account_type_id' })

  const accountTypesQuery = useAccountTypesByCoreTypeQuery(coreType || undefined)
  const detailTypesQuery = useDetailTypesByAccountTypeQuery(accountTypeId ? Number(accountTypeId) : undefined)

  // Form Reset
  useEffect(() => {
    if (!defaultValues) {
      reset(baseDefaults)

      return
    }

    reset({ ...baseDefaults, ...defaultValues })
  }, [defaultValues, reset])

  // reset form every time drawer closes
  useEffect(() => {
    if (!isOpen) {
      reset(baseDefaults)
    }
  }, [isOpen, reset])

  // Set Detail type dropdown to null if account type changes

  const submit: SubmitHandler<AccountCreateFormValues> = values => {
    const payload: AccountCreateSubmitValues = accountCreateSchema.parse(values)

    onSubmit(payload)
  }

  return (
    <Stack component={'form'} onSubmit={handleSubmit(submit)} sx={{ width: '100%', p: 5 }} spacing={5}>
      <Typography variant={'h5'}>Account Details</Typography>

      <CustomTextField
        id='name'
        label='Name'
        autoComplete='on'
        disabled={isSubmitting}
        fullWidth
        {...register('name')}
        error={!!errors.name}
        helperText={errors.name?.message}
      />

      <Controller
        name={'type'}
        control={control}
        disabled={isSubmitting}
        render={({ field }) => (
          <FormControl fullWidth required>
            <InputLabel id='type-label' error={!!errors.type}>
              Core Type
            </InputLabel>
            <Select
              id='core-type'
              labelId='type-label'
              {...field}
              label='Core Type'
              error={!!errors.type}
              onChange={event => {
                const nextType = event.target.value
                const hasTypeChanged = nextType !== field.value

                field.onChange(nextType)

                if (!hasTypeChanged) return

                setValue('account_type_id', '')
                setValue('detail_type_id', '')
                setValue('parent_id', null)
              }}
              inputProps={{ name: field.name, id: 'type-select-input' }}
            >
              <MenuItem value='none'>None</MenuItem>
              <MenuItem value='asset'>Asset</MenuItem>
              <MenuItem value='liability'>Liability</MenuItem>
              <MenuItem value='equity'>Equity</MenuItem>
              <MenuItem value='revenue'>Revenue</MenuItem>
              <MenuItem value='expense'>Expense</MenuItem>
            </Select>
            <FormHelperText error={!!errors.type} sx={{ mb: 3 }}>
              {errors.type?.message ?? 'Select the core type.'}
            </FormHelperText>
          </FormControl>
        )}
      />

      <Controller
        name={'account_type_id'}
        control={control}
        disabled={isSubmitting}
        render={({ field }) => (
          <FormControl fullWidth required>
            <InputLabel id='account-type-label' error={!!errors.account_type_id}>
              Account Type
            </InputLabel>
            <Select
              {...field}
              disabled={!coreType}
              id='account-type'
              labelId='account-type-label'
              label='Account Type'
              error={!!errors.account_type_id}
              onChange={e => field.onChange(e.target.value)}
              inputProps={{ name: field.name, id: 'account-type-select-input' }}
            >
              <MenuItem value='none'>None</MenuItem>
              {(accountTypesQuery.data?.data ?? []).map(x => (
                <MenuItem key={x.id} value={x.id}>
                  {x.name}
                </MenuItem>
              ))}
            </Select>
            <FormHelperText error={!!errors.account_type_id} sx={{ mb: 3 }}>
              {errors.account_type_id?.message ?? 'Select the account type.'}
            </FormHelperText>
          </FormControl>
        )}
      />

      <Controller
        name={'detail_type_id'}
        control={control}
        disabled={isSubmitting}
        render={({ field }) => (
          <FormControl fullWidth required>
            <InputLabel id='detail-type-label' error={!!errors.detail_type_id}>
              Detail Type
            </InputLabel>
            <Select
              {...field}
              id={'detail-type'}
              labelId='detail-type-label'
              label='Detail Type'
              error={!!errors.detail_type_id}
              value={field.value}
              onChange={e => field.onChange(e.target.value)}
              disabled={!accountTypeId}
              inputProps={{ name: field.name, id: 'detail-type-select-input' }}
            >
              <MenuItem value='none'>None</MenuItem>
              {(detailTypesQuery.data?.data ?? []).map(x => (
                <MenuItem key={x.id} value={x.id}>
                  {x.name}
                </MenuItem>
              ))}
            </Select>
            <FormHelperText error={!!errors.detail_type_id} sx={{ mb: 3 }}>
              {errors.detail_type_id?.message ?? 'Select the detail type.'}
            </FormHelperText>
          </FormControl>
        )}
      />

      <Controller
        name={'parent_id'}
        control={control}
        render={({ field }) => (
          <FormControl fullWidth>
            <InputLabel id='parent-account-label' error={!!errors.parent_id}>
              Parent Account
            </InputLabel>
            <Select
              disabled={isSubmitting}
              {...field}
              labelId='parent-account-label'
              label='Parent Account'
              error={!!errors.parent_id}
              value={field.value ?? ''}
              onChange={event => field.onChange(event.target.value || null)}
            >
              <MenuItem value=''>None (Top-level)</MenuItem>
              {(parentOptions ?? [])
                .filter(option => option.type === coreType && !option.is_posting)
                .map(option => (
                  <MenuItem key={option.id} value={option.id}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, pl: option.level * 3 }}>
                      <i className='bx bx-folder' style={{ fontSize: 16 }} />
                      <Typography variant='body2' sx={{ fontWeight: option.has_children ? 600 : 400 }}>
                        {option.code} - {option.name}
                      </Typography>
                    </Box>
                  </MenuItem>
                ))}
            </Select>
            <FormHelperText error={!!errors.parent_id} sx={{ mb: 3 }}>
              {errors.parent_id?.message ?? 'Choose a parent account for hierarchy.'}
            </FormHelperText>
          </FormControl>
        )}
      />

      <Controller
        name={'is_posting'}
        control={control}
        render={({ field }) => (
          <Box>
            <FormControlLabel
              disabled={isSubmitting}
              control={
                <Switch
                  id='account-posting'
                  size='medium'
                  checked={Boolean(field.value)}
                  onChange={(_, checked) => field.onChange(checked)}
                />
              }
              label={field.value ? 'Posting Account' : 'Non-Posting Folder Account'}
            />
            <FormHelperText sx={{ mt: -1 }}>
              Set as non-posting if this account will contain child accounts.
            </FormHelperText>
          </Box>
        )}
      />

      <CustomTextField
        id='description'
        label='Description'
        disabled={isSubmitting}
        fullWidth
        multiline
        minRows={3}
        {...register('description')}
        error={!!errors.description}
        helperText={errors.description?.message}
      />

      <Controller
        name={'contra_account_id'}
        control={control}
        render={({ field }) => (
          <FormControl fullWidth>
            <InputLabel id='contra-account-label' error={!!errors.contra_account_id}>
              Contra Account
            </InputLabel>
            <Select
              disabled={isSubmitting}
              {...field}
              labelId='contra-account-label'
              label='Contra Account'
              error={!!errors.contra_account_id}
              value={field.value ?? ''}
              onChange={event => field.onChange(event.target.value || null)}
            >
              <MenuItem value=''>None</MenuItem>
              {(contraOptions ?? []).map(option => (
                <MenuItem key={option.id} value={option.id}>
                  {option.code} - {option.name}
                </MenuItem>
              ))}
            </Select>
            <FormHelperText error={!!errors.contra_account_id} sx={{ mb: 3 }}>
              {errors.contra_account_id?.message ?? 'Optional. Select the base account if this is contra.'}
            </FormHelperText>
          </FormControl>
        )}
      />

      {actionType === 'update' && (
        <Controller
          name={'status'}
          control={control}
          render={({ field }) => (
            <FormControlLabel
              disabled={isSubmitting}
              control={
                <Switch
                  id='account-status'
                  size={'medium'}
                  checked={field.value === 'active'}
                  onChange={(_, checked) => field.onChange(checked ? 'active' : 'inactive')}
                />
              }
              label='Active'
            />
          )}
        />
      )}
      <Stack direction='row' spacing={2} justifyContent='flex-start'>
        <Button type='submit' variant='contained' disabled={isSubmitting}>
          {actionType === 'create' ? 'Create' : 'Save'}
        </Button>
        <Button
          type={'reset'}
          color={isDirty ? 'error' : 'secondary'}
          variant='tonal'
          onClick={() => {
            reset(defaultValues ? { ...baseDefaults, ...defaultValues } : (baseDefaults as AccountCreateFormValues))
            onDiscard()
          }}
        >
          {isDirty ? 'Discard' : 'Cancel'}
        </Button>
      </Stack>
    </Stack>
  )
}

export default AccountsForm
