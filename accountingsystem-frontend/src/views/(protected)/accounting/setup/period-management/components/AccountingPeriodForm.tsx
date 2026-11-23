'use client'

import React, { useEffect } from 'react'

import { Controller, type SubmitHandler, useForm } from 'react-hook-form'

import Grid from '@mui/material/Grid'

import { Box, Stack } from '@mui/material'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'

import { format, startOfMonth } from 'date-fns'

import { zodResolver } from '@hookform/resolvers/zod'

import FormControlLabel from '@mui/material/FormControlLabel'

import Switch from '@mui/material/Switch'

import CustomTextField from '@core/components/mui/TextField'

import AppReactDatepicker from '@/libs/styles/AppReactDatepicker'

import {
  type CreateFiscalYearFormValues,
  createFiscalYearSchema,
  type CreateFiscalYearSubmitValues
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.schema'

// Third-party Imports

type AccountsPeriodFormProps = {
  actionType: 'create' | 'update'
  onSubmit: (values: CreateFiscalYearSubmitValues) => void | Promise<void>
  onDiscard: () => void
  isSubmitting?: boolean
  isOpen: boolean
  onDirtyChange?: (dirty: boolean) => void
}

const defaultStartDate = format(startOfMonth(new Date()), 'yyyy-MM-dd')

const baseDefaults: CreateFiscalYearFormValues = {
  code: '',
  name: '',
  start_date: defaultStartDate,
  end_date: '',
  is_current: false
}

function AccountingPeriodForm({
  actionType,
  onDiscard,
  isSubmitting,
  onSubmit,
  isOpen,
  onDirtyChange
}: AccountsPeriodFormProps) {
  /**
   * Custom hook that returns the form controller, form field register, form submission handler,
   * form reset function, and form value setter. The form schema is defined by the `createFiscalYearSchema` type.
   *
   * @return {Object} An object containing the `control`, `register`, `handleSubmit`, `reset`, and `setValue` functions.
   */
  const {
    control,
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    formState: { errors, isDirty }
  } = useForm<CreateFiscalYearFormValues>({
    resolver: zodResolver(createFiscalYearSchema),
    defaultValues: baseDefaults
  })

  const startDate = watch('start_date')

  /**
   * Calls the `onDirtyChange` function with the current form's `isDirty` state whenever it changes.
   * This ensures that the parent component is aware of whether the form has been modified or not.
   */
  useEffect(() => {
    onDirtyChange?.(isDirty)
  }, [isDirty, onDirtyChange])

  // Resets drawer everytime after closing
  useEffect(() => {
    if (!isOpen) {
      reset(baseDefaults)
    }
  }, [isOpen, reset])

  // Handle Submit
  const submit: SubmitHandler<CreateFiscalYearFormValues> = values => {
    const payload: CreateFiscalYearSubmitValues = createFiscalYearSchema.parse(values)

    onSubmit(payload)
  }

  // Automate Fiscal Year creation
  useEffect(() => {
    if (!startDate) return

    const start = startOfMonth(new Date(`${startDate}T00:00:00`))

    // End date: last day of the month BEFORE the same month next year
    const end = new Date(start.getFullYear() + 1, start.getMonth(), 0)

    // Write back normalized start + computed end
    setValue('start_date', format(start, 'yyyy-MM-dd'), { shouldValidate: true, shouldDirty: true })
    setValue('end_date', format(end, 'yyyy-MM-dd'), { shouldValidate: true, shouldDirty: true })
  }, [startDate, setValue])

  return (
    <>
      <Stack
        component={'form'}
        onSubmit={handleSubmit(submit)}
        sx={{ width: '100%', p: 8, gap: 5, height: '100%' }}
        spacing={5}
      >
        <Stack direction={'column'} spacing={2}>
          <Typography variant={'h5'}>Fiscal Year Details</Typography>

          <CustomTextField
            id='code'
            label='Code'
            autoComplete='on'
            disabled={isSubmitting}
            fullWidth
            {...register('code')}
            required={true}
            error={!!errors.name}
            helperText={errors.name?.message}
          />

          <CustomTextField
            id='name'
            label='Name'
            autoComplete='on'
            disabled={isSubmitting}
            fullWidth
            {...register('name')}
            required={true}
            error={!!errors.name}
            helperText={errors.name?.message}
          />

          <Grid container spacing={6}>
            <Grid size={{ xs: 12, lg: 6 }}>
              <Controller
                name='start_date'
                control={control}
                render={({ field, fieldState }) => (
                  <AppReactDatepicker
                    showYearDropdown
                    disabled={isSubmitting}
                    id='start-date'
                    onChange={(date: Date | null) => field.onChange(date ? format(date, 'yyyy-MM-dd') : '')}
                    selected={field.value ? new Date(`${field.value}T00:00:00`) : startOfMonth(new Date())}
                    filterDate={(date: Date) => date.getDate() === 1}
                    customInput={
                      <CustomTextField
                        label='Year Start Date'
                        fullWidth
                        error={!!fieldState.error}
                        helperText={fieldState.error?.message}
                      />
                    }
                  />
                )}
              />
            </Grid>

            <Grid size={{ xs: 12, lg: 6 }}>
              <Controller
                name='end_date'
                control={control}
                render={({ field, fieldState }) => (
                  <AppReactDatepicker
                    showYearDropdown
                    id='end-date'
                    disabled={true}
                    onChange={() => {}}
                    selected={field.value ? new Date(`${field.value}T00:00:00`) : null}
                    customInput={
                      <CustomTextField
                        slotProps={{ htmlInput: { readOnly: true } }}
                        label='Year End Date'
                        fullWidth
                        error={!!fieldState.error}
                        helperText={'Year end date based from start date'}
                      />
                    }
                  />
                )}
              />
            </Grid>
          </Grid>
          <Box>
            <Controller
              name='is_current'
              control={control}
              render={({ field }) => (
                <>
                  <FormControlLabel
                    disabled={isSubmitting}
                    control={
                      <Switch
                        id='is-current'
                        checked={Boolean(field.value)}
                        onChange={(_, checked) => field.onChange(checked)}
                        size='medium'
                      />
                    }
                    label='Current Year Period'
                  />
                  <Chip
                    icon={<i className='bx bx-error' style={{ marginTop: 4 }} />}
                    sx={{
                      height: 'auto',
                      alignItems: 'flex-start',
                      '& .MuiChip-label': {
                        display: 'block',
                        whiteSpace: 'normal',
                        textAlign: 'left'
                      }
                    }}
                    color='warning'
                    variant='tonal'
                    label='Enabling this will disable all other fiscal years and may affect posting.'
                  />
                </>
              )}
            />
          </Box>
        </Stack>

        <Stack direction='row' spacing={2} justifyContent='flex-start'>
          <Button type='submit' variant='contained' disabled={isSubmitting}>
            {actionType === 'create' ? 'Create' : 'Save'}
          </Button>
          <Button
            color={isDirty ? 'error' : 'secondary'}
            variant='tonal'
            onClick={() => {
              onDiscard() // closes drawer
            }}
          >
            {isDirty ? 'Discard' : 'Cancel'}
          </Button>
        </Stack>
      </Stack>
    </>
  )
}

export default AccountingPeriodForm
