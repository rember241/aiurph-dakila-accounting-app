import React from 'react'

import { Stack } from '@mui/material'

import CustomKpiCard from '@components/app/CustomKPICard'

import type { AccountStats } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

type AccountsHeaderStatsProps = {
  stats?: AccountStats
  loading?: boolean
  error?: string | null
}

function AccountsHeaderStats({ stats, loading, error }: AccountsHeaderStatsProps) {
  const activePercentage = stats?.active && stats?.total ? `${Math.round((stats?.active / stats?.total) * 100)}%` : '—'

  return (
    <Stack direction='row' spacing={3}>
      <CustomKpiCard
        icon={<i className={'bx-briefcase'} style={{ fontSize: '24px' }} />}
        label='Total Accounts'
        value={stats?.total}
        loading={loading}
        error={error}
      />
      <CustomKpiCard
        icon={<i className={'bx-check-circle'} style={{ fontSize: '24px' }} />}
        label={`Active | ${activePercentage}`}
        value={stats?.active}
        loading={loading}
        error={error}
      />

      {/*<CustomKpiCard*/}
      {/*  titleColor={'text-success'}*/}
      {/*  label='Total Debit'*/}
      {/*  value={'$7,250,000'}*/}
      {/*  loading={loading}*/}
      {/*  error={error}*/}
      {/*/>*/}
      {/*<CustomKpiCard*/}
      {/*  icon={<i className={'bx-bank'} style={{ fontSize: '24px' }} />}*/}
      {/*  titleColor={'text-error'}*/}
      {/*  label='Total Credit'*/}
      {/*  value={'$1,350,500'}*/}
      {/*  loading={loading}*/}
      {/*  error={error}*/}
      {/*/>*/}
    </Stack>
  )
}

export default AccountsHeaderStats
