import { useEffect, useMemo } from 'react'

import { Controller, type SubmitHandler, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import FormHelperText from '@mui/material/FormHelperText'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomDrawer from '@components/app/CustomDrawer'
import CustomTextField from '@core/components/mui/TextField'

import { orgUnitFormSchema } from '@/features/(protected)/users/company-structure/model/company-structure.schema'
import {
  getAllowedParentTypes,
  getOrgUnitTypeLabel
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type {
  OrgUnitFormValues,
  OrgUnitParentOption,
  OrgUnitRecord
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

type OrgUnitFormDrawerProps = {
  open: boolean
  mode: 'create' | 'edit'
  defaultOrgUnit: OrgUnitRecord | null
  initialValues?: Partial<OrgUnitFormValues> | null
  parentOptions: OrgUnitParentOption[]
  isSubmitting?: boolean
  onClose: () => void
  onSubmit: (values: OrgUnitFormValues) => void | Promise<void>
}

const baseDefaults: OrgUnitFormValues = {
  name: '',
  code: '',
  type: 'department',
  parentId: null,
  location: '',
  description: ''
}

function OrgUnitFormDrawer({
  open,
  mode,
  defaultOrgUnit,
  initialValues = null,
  parentOptions,
  isSubmitting,
  onClose,
  onSubmit
}: OrgUnitFormDrawerProps) {
  const {
    control,
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors }
  } = useForm<OrgUnitFormValues>({
    resolver: zodResolver(orgUnitFormSchema),
    defaultValues: baseDefaults
  })

  const type = watch('type')
  const allowedParentTypes = useMemo(() => getAllowedParentTypes(type), [type])

  const availableParentOptions = useMemo(() => {
    if (type === 'head_office') {
      return []
    }

    return parentOptions.filter(option => allowedParentTypes.includes(option.type))
  }, [allowedParentTypes, parentOptions, type])

  useEffect(() => {
    if (!open) {
      reset(baseDefaults)

      return
    }

    if (!defaultOrgUnit) {
      reset({
        ...baseDefaults,
        ...initialValues
      })

      return
    }

    reset({
      name: defaultOrgUnit.name,
      code: defaultOrgUnit.code,
      type: defaultOrgUnit.type,
      parentId: defaultOrgUnit.parentId,
      location: defaultOrgUnit.location ?? '',
      description: defaultOrgUnit.description ?? ''
    })
  }, [defaultOrgUnit, initialValues, open, reset])

  useEffect(() => {
    if (type !== 'head_office') {
      return
    }

    setValue('parentId', null, { shouldDirty: true, shouldValidate: true })
  }, [setValue, type])

  const submit: SubmitHandler<OrgUnitFormValues> = values => {
    onSubmit(orgUnitFormSchema.parse(values))
  }

  return (
    <CustomDrawer
      open={open}
      onClose={onClose}
      width={640}
      title={mode === 'create' ? 'Create Org Unit' : 'Edit Org Unit'}
      subtitle={
        mode === 'create'
          ? 'Add a branch, department, team, or head office unit to the company structure.'
          : 'Update the org unit details and reporting structure.'
      }
    >
      <Stack component='form' spacing={4} sx={{ p: 5 }} onSubmit={handleSubmit(submit)}>
        <Typography variant='h5'>Org Unit Details</Typography>

        <CustomTextField
          label='Org Unit Name'
          fullWidth
          disabled={isSubmitting}
          placeholder='Finance Department'
          {...register('name')}
          error={!!errors.name}
          helperText={errors.name?.message}
        />

        <Stack direction={{ xs: 'column', md: 'row' }} spacing={3}>
          <CustomTextField
            label='Code'
            fullWidth
            disabled={isSubmitting}
            placeholder='DEPT-FIN'
            {...register('code')}
            error={!!errors.code}
            helperText={errors.code?.message ?? 'Use uppercase letters, numbers, and hyphens.'}
          />

          <Controller
            name='type'
            control={control}
            render={({ field }) => (
              <FormControl fullWidth error={!!errors.type}>
                <InputLabel id='org-unit-type-label'>Unit Type</InputLabel>
                <Select {...field} labelId='org-unit-type-label' label='Unit Type' disabled={isSubmitting}>
                  {(['head_office', 'branch', 'department', 'team'] as const).map(option => (
                    <MenuItem key={option} value={option}>
                      {getOrgUnitTypeLabel(option)}
                    </MenuItem>
                  ))}
                </Select>
                <FormHelperText>{errors.type?.message}</FormHelperText>
              </FormControl>
            )}
          />
        </Stack>

        <Controller
          name='parentId'
          control={control}
          render={({ field }) => (
            <FormControl fullWidth error={!!errors.parentId}>
              <InputLabel id='org-unit-parent-label'>Reports To</InputLabel>
              <Select
                {...field}
                value={field.value ?? ''}
                labelId='org-unit-parent-label'
                label='Reports To'
                disabled={isSubmitting || type === 'head_office'}
                onChange={event => field.onChange(event.target.value || null)}
              >
                {type === 'head_office' || type === 'branch' ? <MenuItem value=''>No parent</MenuItem> : null}
                {availableParentOptions.map(option => (
                  <MenuItem key={option.id} value={option.id}>
                    {option.name} ({getOrgUnitTypeLabel(option.type)})
                  </MenuItem>
                ))}
              </Select>
              <FormHelperText>
                {errors.parentId?.message ??
                  (type === 'head_office'
                    ? 'Head office units stay at the top of the structure.'
                    : 'Choose the immediate parent for this org unit.')}
              </FormHelperText>
            </FormControl>
          )}
        />

        <CustomTextField
          label='Location'
          fullWidth
          disabled={isSubmitting}
          placeholder='Head Office'
          {...register('location')}
          error={!!errors.location}
          helperText={errors.location?.message ?? 'Optional. Use this for the site or operating location.'}
        />

        <CustomTextField
          label='Description'
          fullWidth
          multiline
          minRows={3}
          disabled={isSubmitting}
          placeholder='Shared services and management reporting.'
          {...register('description')}
          error={!!errors.description}
          helperText={errors.description?.message ?? 'Optional. Briefly describe the unit responsibility.'}
        />

        <Stack direction='row' spacing={2} justifyContent='flex-end'>
          <Button variant='text' color='secondary' onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button type='submit' variant='contained' disabled={isSubmitting}>
            {mode === 'create' ? 'Create Org Unit' : 'Save Changes'}
          </Button>
        </Stack>
      </Stack>
    </CustomDrawer>
  )
}

export default OrgUnitFormDrawer
