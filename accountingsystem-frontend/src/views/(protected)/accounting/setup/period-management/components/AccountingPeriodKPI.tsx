'use client'

import React from 'react'

import Typography from '@mui/material/Typography'

import { Stack } from '@mui/material'
import { format } from 'date-fns'

import CustomKPICard from '@components/app/CustomKPICard'

import LinearProgressWithLabel from '@components/app/LinearProgressWithLabel'

import type { PeriodManagementKpiResponse } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

type AccountingPeriodKPIProps = {
  kpi: PeriodManagementKpiResponse | null
  isLoading?: boolean
}

function AccountingPeriodKpi({ kpi, isLoading = false }: AccountingPeriodKPIProps) {
  const fiscalYear = kpi?.current_fiscal_year ?? null
  const daysLeftInCurrentYear = kpi?.days_left_in_current_year ?? 0

  const closingProgress = kpi?.closing_progress ?? {
    closed_periods: 0,
    total_periods: 0,
    percentage: 0
  }

  const startDateFormatted = fiscalYear ? format(new Date(fiscalYear.start_date), 'MMMM yyyy') : ''
  const endDateFormatted = fiscalYear ? format(new Date(fiscalYear.end_date), 'MMMM yyyy') : ''

  return (
    <>
      <Stack spacing={6} direction='row'>
        <CustomKPICard
          label='Current Year Period'
          iconString='bx-tachometer'
          iconColor={fiscalYear?.status === 'closed' ? 'error' : 'success'}
          value={fiscalYear?.status ? fiscalYear.status.charAt(0).toUpperCase() + fiscalYear.status.slice(1) : ''}
          titleColor='text-success'
          loading={isLoading}
          error={null}
        >
          <Typography variant='h6' color='text.secondary'>
            {daysLeftInCurrentYear} days left
          </Typography>
          <Typography variant='caption'>
            {startDateFormatted} to {endDateFormatted}
          </Typography>
        </CustomKPICard>

        <CustomKPICard
          label='Closing Progress'
          iconString='bx-pie-chart'
          iconColor='primary'
          value={`${closingProgress.closed_periods}/${closingProgress.total_periods} Periods`}
          titleColor='text-primary'
          loading={isLoading}
          error={null}
        >
          <LinearProgressWithLabel value={closingProgress.percentage} />
          <Typography variant='subtitle2' color='text.secondary'>
            {`${Math.round(closingProgress.percentage)}% of ${closingProgress.total_periods}`}
          </Typography>
        </CustomKPICard>

        <CustomKPICard
          label='Blocked Transactions'
          iconString='bx-block'
          iconColor='secondary'
          value='12'
          titleColor='text-secondary'
          loading={isLoading}
          error={null}
        >
          <Typography variant='caption'>To be implemented</Typography>
        </CustomKPICard>
      </Stack>
    </>
  )
}

export default AccountingPeriodKpi
