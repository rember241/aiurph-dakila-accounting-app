'use client'

import Link from 'next/link'

import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { format, parseISO } from 'date-fns'

import CustomPageHeader from '@components/app/CustomPageHeader'

import { RankedAccounts } from './components/RankedAccounts'
import { DashboardChart } from './components/DashboardChart'
import { DashboardKpiCard } from './components/DashboardKpiCard'
import { DashboardQuickActions } from './components/DashboardQuickActions'
import { ChartLegend } from './components/ChartLegend'
import { useDashboardData } from './hooks/useDashboardData'
import type { DashboardMonth } from './hooks/useDashboardData'

const ledgerHref = '/accounting/books/ledger'
const amounts = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function amount(value: string | null | undefined) {
  return value == null ? '—' : amounts.format(Number(value))
}

export function DashboardHomePage() {
  const { metrics, today, canAny } = useDashboardData()
  const months = metrics.data?.months ?? []
  const current = months.at(-1)
  const labels = months.map(({ month }) => format(parseISO(`${month}-01`), 'MMM yy'))
  const monthLabel = format(today, 'MMMM yyyy')
  const dateLabel = format(today, 'MMM d, yyyy')
  const chartState = { labels, loading: metrics.isPending }

  const trend = (key: Exclude<keyof DashboardMonth, 'month'>) =>
    months.map(month => (month[key] === null ? null : Number(month[key])))

  return (
    <Stack spacing={5}>
      <CustomPageHeader title='Business overview'>
        <Button
          component={Link}
          href={ledgerHref}
          color='secondary'
          variant='outlined'
          size='small'
          startIcon={<i className='bx-book-open' />}
        >
          Open General Ledger
        </Button>
      </CustomPageHeader>
      <DashboardQuickActions canAny={canAny} />
      <Typography variant='body2' color='text.secondary'>
        Posted through {dateLabel} · Ledger base currency
      </Typography>

      {metrics.isError && (
        <Alert
          severity='error'
          action={
            <Button color='inherit' onClick={() => void metrics.refetch()}>
              Retry
            </Button>
          }
        >
          The dashboard could not load the latest ledger figures.
        </Alert>
      )}

      <Grid container spacing={4}>
        <Grid size={{ xs: 12, lg: 5 }}>
          <DashboardChart
            featured
            title='Cash balance'
            subtitle={`Cash and bank accounts as of ${dateLabel}`}
            type='area'
            series={[{ name: 'Cash balance', tone: 'primary', data: trend('cashBalance') }]}
            emptyMessage={
              metrics.isError ? 'Cash balance unavailable.' : 'No cash or bank accounts are classified in the ledger.'
            }
            {...chartState}
          >
            <Typography variant='h2' component='p' sx={{ mb: 2, overflowWrap: 'anywhere' }}>
              {amount(current?.cashBalance)}
            </Typography>
          </DashboardChart>
        </Grid>

        <Grid size={{ xs: 12, lg: 7 }}>
          <DashboardChart
            title='Income and expenses'
            subtitle='Monthly totals · Last six months'
            type='bar'
            series={[
              { name: 'Income', tone: 'success', data: trend('totalIncome') },
              { name: 'Expenses', tone: 'warning', data: trend('totalExpenses') }
            ]}
            emptyMessage='Income and expense history unavailable.'
            {...chartState}
          />
        </Grid>

        {(
          [
            { title: 'Net profit', key: 'netProfit', tone: 'primary' },
            { title: 'Total income', key: 'totalIncome', tone: 'success' },
            { title: 'Total expenses', key: 'totalExpenses', tone: 'warning' }
          ] as const
        ).map(({ title, key, tone }) => (
          <Grid key={key} size={{ xs: 12, sm: 6, lg: 3 }}>
            <DashboardKpiCard
              title={title}
              value={amount(current?.[key])}
              caption={`${monthLabel} to date`}
              tone={tone}
              trend={months.map(month => Number(month[key]))}
            />
          </Grid>
        ))}

        <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
          <DashboardKpiCard
            title='Net profit margin'
            value={
              current && Number(current.totalIncome) > 0
                ? new Intl.NumberFormat(undefined, { style: 'percent', maximumFractionDigits: 1 }).format(
                    Number(current.netProfit) / Number(current.totalIncome)
                  )
                : '—'
            }
            caption={`${monthLabel} to date`}
            tone='info'
            trend={[]}
          />
        </Grid>

        {(
          [
            { key: 'totalIncome', title: 'Top 10 income accounts', icon: 'bx-trending-up' },
            { key: 'totalExpenses', title: 'Top 10 expenses', icon: 'bx-receipt' }
          ] as const
        ).map(({ key, title, icon }) => (
          <Grid key={key} size={{ xs: 12, md: 6 }}>
            <RankedAccounts
              title={title}
              icon={icon}
              period={monthLabel}
              rows={metrics.data?.rankings?.[key]}
              loading={metrics.isPending}
            />
          </Grid>
        ))}

        <Grid size={{ xs: 12, lg: 5 }}>
          <DashboardChart
            title='Net profit trend'
            subtitle='Income less expenses · Last six months'
            type='area'
            series={[{ name: 'Net profit', tone: 'primary', data: trend('netProfit') }]}
            emptyMessage='Net profit history unavailable.'
            {...chartState}
          />
        </Grid>

        <Grid size={{ xs: 12, lg: 7 }}>
          <DashboardChart
            title='Receivables and payables'
            subtitle='Balances at the end of each month · Current month to date'
            type='bar'
            series={[
              { name: 'Receivables', tone: 'info', data: trend('receivables') },
              { name: 'Payables', tone: 'warning', data: trend('payables') }
            ]}
            emptyMessage={
              metrics.isError
                ? 'Receivables and payables unavailable.'
                : 'No receivable or payable accounts are classified in the ledger.'
            }
            {...chartState}
          >
            <ChartLegend
              items={[
                { label: 'Accounts receivable', value: amount(current?.receivables), tone: 'info' },
                { label: 'Accounts payable', value: amount(current?.payables), tone: 'warning' }
              ]}
            />
          </DashboardChart>
        </Grid>
      </Grid>
    </Stack>
  )
}
