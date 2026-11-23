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
import LinearProgress from '@mui/material/LinearProgress'

import { zodResolver } from '@hookform/resolvers/zod'

import { format } from 'date-fns'

import CustomTextField from '@core/components/mui/TextField'

import {
  type UpdateFiscalYearFormValues,
  updateFiscalYearSchema
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.schema'

import type { FiscalYear } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

type FiscalYearSettingsFormProps = {
  fiscalYearData: FiscalYear | null
  periodStats?: {
    total: number
    closed: number
  } | null
  isLoadingPeriodStats?: boolean
  onSubmit: (values: UpdateFiscalYearFormValues) => void | Promise<void>
  onDiscard: () => void
  isSubmitting?: boolean
  isOpen: boolean
  onDirtyChange?: (dirty: boolean) => void
}

function FiscalYearSettingsForm({
  fiscalYearData,
  periodStats,
  isLoadingPeriodStats,
  onDiscard,
  isSubmitting,
  onSubmit,
  isOpen,
  onDirtyChange
}: FiscalYearSettingsFormProps) {
  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { isDirty, errors }
  } = useForm<UpdateFiscalYearFormValues>({
    resolver: zodResolver(updateFiscalYearSchema),
    defaultValues: {
      code: fiscalYearData?.code ?? '',
      name: fiscalYearData?.name ?? '',
      status: fiscalYearData?.status ?? 'open'
    }
  })

  useEffect(() => {
    onDirtyChange?.(isDirty)
  }, [isDirty, onDirtyChange])

  useEffect(() => {
    if (!isOpen) return

    reset({
      code: fiscalYearData?.code ?? '',
      name: fiscalYearData?.name ?? '',
      status: fiscalYearData?.status ?? 'open'
    })
  }, [fiscalYearData?.code, fiscalYearData?.name, fiscalYearData?.status, isOpen, reset])

  const submit: SubmitHandler<UpdateFiscalYearFormValues> = values => {
    const payload = updateFiscalYearSchema.parse(values)

    onSubmit(payload)
  }

  const status = useWatch({ control, name: 'status' })

  const lockMeta = React.useMemo(() => {
    if (status === 'closed') {
      return {
        color: 'error' as const,
        icon: 'bx bx-lock-alt',
        label: (
          <span>
            Closed and Locked.
            <br />
            Note: Closing requires all periods in this fiscal year to be closed.
          </span>
        )
      }
    }

    return {
      color: 'success' as const,
      icon: 'bx bx-lock-open-alt',
      label: (
        <span>
          Open and Active.
          <br />
          Note: Reopening keeps all period statuses unchanged.
        </span>
      )
    }
  }, [status])

  const totalPeriods = periodStats?.total ?? fiscalYearData?.periods_count ?? 0
  const closedPeriods = periodStats?.closed ?? fiscalYearData?.closed_periods_count ?? 0
  const openPeriods = Math.max(totalPeriods - closedPeriods, 0)
  const closedPercentage = totalPeriods > 0 ? (closedPeriods / totalPeriods) * 100 : 0

  return (
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
          <Typography variant={'h5'}>{`Fiscal Year ${fiscalYearData?.code ?? ''} Details`}</Typography>

          <Typography variant='body2' color={'text.secondary'}>
            Created{' '}
            <strong>
              {fiscalYearData?.created_at ? format(new Date(fiscalYearData.created_at), 'PPP hh:mm a') : 'N/A'}
            </strong>
          </Typography>
        </Stack>

        <Grid container spacing={6}>
          <Grid size={{ xs: 12, lg: 6 }}>
            <CustomTextField
              id='code'
              label='Code'
              fullWidth
              disabled={isSubmitting}
              {...register('code')}
              error={!!errors.code}
              helperText={errors.code?.message}
            />
          </Grid>

          <Grid size={{ xs: 12, lg: 6 }}>
            <CustomTextField
              id='name'
              label='Name'
              fullWidth
              disabled={isSubmitting}
              {...register('name')}
              error={!!errors.name}
              helperText={errors.name?.message}
            />
          </Grid>
        </Grid>

        <Grid container spacing={6}>
          <Grid size={{ xs: 12, lg: 6 }}>
            <CustomTextField
              label='Year Start Date'
              fullWidth
              disabled={true}
              value={fiscalYearData?.start_date ? format(new Date(fiscalYearData.start_date), 'MMMM d, yyyy') : ''}
            />
          </Grid>

          <Grid size={{ xs: 12, lg: 6 }}>
            <CustomTextField
              label='Year End Date'
              fullWidth
              disabled={true}
              value={fiscalYearData?.end_date ? format(new Date(fiscalYearData.end_date), 'MMMM d, yyyy') : ''}
            />
          </Grid>
        </Grid>

        <Stack direction='row' spacing={2}>
          <Chip
            icon={<i className={fiscalYearData?.is_current ? 'bx bx-check-circle' : 'bx bx-minus-circle'} />}
            label={fiscalYearData?.is_current ? 'Current Fiscal Year' : 'Not Current'}
            color={fiscalYearData?.is_current ? 'primary' : 'secondary'}
            variant='tonal'
          />
          <Chip
            icon={<i className='bx bx-layer' />}
            label={`${fiscalYearData?.periods_count ?? 0} Periods`}
            color='info'
            variant='tonal'
          />
        </Stack>

        <Stack spacing={2}>
          <Typography variant={'h6'}>{'Period Closure Progress'}</Typography>
          <Stack direction='row' spacing={2}>
            <Chip
              icon={<i className='bx bx-check-circle' />}
              label={`${closedPeriods} Closed`}
              color='success'
              variant='tonal'
            />
            <Chip
              icon={<i className='bx bx-lock-open-alt' />}
              label={`${openPeriods} Open / Soft Closed`}
              color='warning'
              variant='tonal'
            />
          </Stack>
          <LinearProgress variant='determinate' value={closedPercentage} color='success' />
          <Typography variant='body2' color='text.secondary'>
            {`${Math.round(closedPercentage)}% complete (${closedPeriods}/${totalPeriods})`}
          </Typography>
          {isLoadingPeriodStats && (
            <Typography variant='caption' color='text.secondary'>
              Updating period stats...
            </Typography>
          )}
        </Stack>

        {fiscalYearData?.closed_at && (
          <Typography variant='body2' color={'text.secondary'}>
            Closed At: <strong>{format(new Date(fiscalYearData.closed_at), 'PPP hh:mm a')}</strong>
          </Typography>
        )}

        <Divider />

        <Stack direction='row' spacing={2}>
          <Chip
            icon={<i className={lockMeta.icon} style={{ marginTop: 4 }} />}
            label={lockMeta.label}
            color={lockMeta.color}
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
            <FormControl sx={{ width: '190px' }}>
              <InputLabel id='fiscal-year-status-select-label'>Fiscal Year Status</InputLabel>
              <Select
                labelId='fiscal-year-status-select-label'
                label='Fiscal Year Status'
                {...field}
                disabled={isSubmitting}
              >
                <MenuItem value={'open'}>Open</MenuItem>
                <MenuItem value={'closed'}>Closed</MenuItem>
              </Select>
            </FormControl>
          )}
        />
      </Box>

      <Stack direction='row' spacing={4} justifyContent='flex-end'>
        <Button type='submit' variant='contained' disabled={isSubmitting || !isDirty}>
          Save
        </Button>
        <Button color={isDirty ? 'error' : 'secondary'} variant='tonal' onClick={onDiscard}>
          {isDirty ? 'Discard' : 'Cancel'}
        </Button>
      </Stack>
    </Stack>
  )
}

export default FiscalYearSettingsForm
