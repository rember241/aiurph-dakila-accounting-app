'use client'

import React, { useEffect } from 'react'

import { Controller, type SubmitHandler, useForm, useWatch } from 'react-hook-form'

import Grid from '@mui/material/Grid'

import { Box, FormControl, InputLabel, Select, Stack } from '@mui/material'
import Typography from '@mui/material/Typography'
import Button from '@mui/material/Button'
import MenuItem from '@mui/material/MenuItem'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'

import { zodResolver } from '@hookform/resolvers/zod'

import { format } from 'date-fns'

import CustomTextField from '@core/components/mui/TextField'

import {
  type TransitionPeriodStatusFormValues,
  transitionPeriodStatusSchema
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.schema'

import type { AccountingPeriod } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

// Third-party Imports

type AccountingPeriodSettingsFormProps = {
  periodData: AccountingPeriod | null
  onSubmit: (values: TransitionPeriodStatusFormValues) => void | Promise<void>
  onDiscard: () => void
  isSubmitting?: boolean
  isOpen: boolean
  onDirtyChange?: (dirty: boolean) => void
}

function AccountingPeriodSettingsForm({
  periodData,
  onDiscard,
  isSubmitting,
  onSubmit,
  isOpen,
  onDirtyChange
}: AccountingPeriodSettingsFormProps) {
  /**
   * Custom hook that returns the form controller, form field register, form submission handler,
   * form reset function, and form value setter. The form schema is defined by the `createFiscalYearSchema` type.
   *
   * @return {Object} An object containing the `control`, `register`, `handleSubmit`, `reset`, and `setValue` functions.
   */
  const {
    control,
    handleSubmit,
    reset,
    formState: { isDirty }
  } = useForm<TransitionPeriodStatusFormValues>({
    resolver: zodResolver(transitionPeriodStatusSchema),
    defaultValues: {
      status: periodData?.status ?? 'open'
    }
  })

  /**
   * Calls the `onDirtyChange` function with the current form's `isDirty` state whenever it changes.
   * This ensures that the parent component is aware of whether the form has been modified or not.
   */
  useEffect(() => {
    onDirtyChange?.(isDirty)
  }, [isDirty, onDirtyChange])

  // Resets form values everytime after closing

  useEffect(() => {
    if (!isOpen) return

    reset({
      status: periodData?.status ?? 'open'
    })
  }, [isOpen, periodData?.status, reset])

  // Handle Submit
  const submit: SubmitHandler<TransitionPeriodStatusFormValues> = values => {
    const payload: TransitionPeriodStatusFormValues = transitionPeriodStatusSchema.parse(values)

    onSubmit(payload)
  }

  // Watch the posting status value to change the chip dynamically
  const status = useWatch({ control, name: 'status' })

  const postingMeta = React.useMemo(() => {
    if (status === 'open') {
      return {
        color: 'success' as const,
        icon: 'bx bx-check-circle',
        label: (
          <span>
            Open — All postings allowed.
            <br />
            Note: Accounting period marked as open allows all postings.
          </span>
        )
      }
    }

    if (status === 'soft_closed') {
      return {
        color: 'warning' as const,
        icon: 'bx bx-error-circle',
        label: (
          <span>
            Soft Close — AP/AR/Inventory locked; GL adjustments allowed.
            <br />
            Note: Subledgers are blocked. Only General Ledger adjustments are allowed.
          </span>
        )
      }
    }

    // closed
    return {
      color: 'error' as const,
      icon: 'bx bx-lock-alt',
      label: (
        <span>
          Closed — No postings allowed.
          <br />
          Note: Accounting period marked as closed does not allow any postings. If you want to reopen the accounting
          period, please contact your administrator.
        </span>
      )
    }
  }, [status])

  const isFiscalYearClosed = periodData?.fiscal_year?.status === 'closed'

  return (
    <>
      <Stack component={'form'} onSubmit={handleSubmit(submit)} sx={{ width: '100%', height: '100%' }} spacing={8}>
        <Stack direction={'column'} spacing={4}>
          <Stack
            direction={'column'}
            spacing={1}
            sx={{
              '& > :not(style)': {
                mb: 0.5
              }
            }}
          >
            <Typography variant={'h5'}>{'Accounting Period ' + periodData?.period_number + ' Details'}</Typography>

            <Typography variant='body2' color={'text.secondary'}>
              Created{' '}
              <strong>{periodData?.created_at ? format(new Date(periodData.created_at), 'PPP hh:mm a') : 'N/A'}</strong>
            </Typography>

            {isFiscalYearClosed && (
              <Typography variant='body2' color='warning.main'>
                Fiscal year must be opened to make changes.
              </Typography>
            )}
          </Stack>

          <CustomTextField
            id='period'
            label='Accounting Period'
            fullWidth
            defaultValue={periodData?.name}
            disabled={true}
          />

          <Grid container spacing={6}>
            <Grid size={{ xs: 12, lg: 6 }}>
              <CustomTextField
                label='Month Start Date'
                fullWidth
                disabled={true}
                value={periodData?.start_date ? format(new Date(periodData.start_date), 'MMMM d, yyyy') : ''}
              />
            </Grid>

            <Grid size={{ xs: 12, lg: 6 }}>
              <CustomTextField
                label='Month End Date'
                fullWidth
                disabled={true}
                value={periodData?.end_date ? format(new Date(periodData.end_date), 'MMMM d, yyyy') : ''}
              />
            </Grid>
          </Grid>

          <Stack spacing={2}>
            <Typography variant={'h6'}>{'Posting Status'}</Typography>
            <Stack direction={'row'} spacing={2}>
              <Chip
                icon={<i className={periodData?.lock_ap ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt'} />}
                label={periodData?.lock_ap ? 'AP Locked' : 'A/P Unlocked'}
                color={periodData?.lock_ap ? 'error' : 'success'}
                variant='tonal'
              />

              <Chip
                icon={<i className={periodData?.lock_ar ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt'} />}
                label={periodData?.lock_ar ? 'A/R Locked' : 'AR Unlocked'}
                color={periodData?.lock_ar ? 'error' : 'success'}
                variant='tonal'
              />

              <Chip
                icon={<i className={periodData?.lock_inventory ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt'} />}
                label={periodData?.lock_inventory ? 'Inventory Locked' : 'Inventory Unlocked'}
                color={periodData?.lock_inventory ? 'error' : 'success'}
                variant='tonal'
              />
            </Stack>

            {periodData?.closed_at && (
              <Typography variant='body2' color={'text.secondary'}>
                Closed At: <strong>{format(new Date(periodData.closed_at), 'PPP hh:mm a')}</strong>
              </Typography>
            )}
          </Stack>

          <Divider />

          <Stack direction='row' spacing={2}>
            <Chip
              icon={<i className={postingMeta.icon} style={{ marginTop: 4 }} />}
              label={postingMeta.label}
              color={postingMeta.color}
              variant='tonal'
              sx={{
                height: 'auto',
                alignItems: 'flex-start',
                '& .MuiChip-label': {
                  display: 'block',
                  whiteSpace: 'normal',
                  textAlign: 'left'
                }
              }}
            />
          </Stack>
        </Stack>

        <Box sx={{ width: '100%' }}>
          <Controller
            name='status'
            control={control}
            render={({ field }) => (
              <FormControl sx={{ width: '150px' }}>
                <InputLabel id='status-select-label'>Period Status</InputLabel>
                <Select
                  labelId='status-select-label'
                  label='Period Status'
                  {...field}
                  disabled={isSubmitting || periodData?.status === 'closed' || isFiscalYearClosed}
                >
                  <MenuItem value={'open'}>Open</MenuItem>
                  <MenuItem value={'soft_closed'}>Soft Closed</MenuItem>
                  <MenuItem value={'closed'}>Closed</MenuItem>
                </Select>
              </FormControl>
            )}
          />
        </Box>

        <Stack direction='row' spacing={4} justifyContent='flex-end'>
          {periodData?.status !== 'closed' && !isFiscalYearClosed && (
            <Button type='submit' variant='contained' disabled={isSubmitting || !isDirty}>
              Save
            </Button>
          )}
          <Button
            color={isDirty ? 'error' : 'secondary'}
            variant='tonal'
            onClick={() => {
              onDiscard()
            }}
          >
            {periodData?.status === 'closed' || isFiscalYearClosed ? 'Close' : isDirty ? 'Discard' : 'Cancel'}
          </Button>
        </Stack>
      </Stack>
    </>
  )
}

export default AccountingPeriodSettingsForm
