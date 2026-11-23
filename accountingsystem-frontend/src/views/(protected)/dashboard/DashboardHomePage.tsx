'use client'

import Link from 'next/link'

import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CardHeader from '@mui/material/CardHeader'
import Grid from '@mui/material/Grid'
import LinearProgress from '@mui/material/LinearProgress'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { DashboardKpiCard } from './components/DashboardKpiCard'
import { FocusMetricCard } from './components/FocusMetricCard'
import { useDashboardData } from './hooks/useDashboardData'

// Reports use the ledger's base amounts. Do not substitute a user's display currency.
const amount = (value: number | undefined) =>
  value === undefined
    ? '—'
    : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export function DashboardHomePage() {
  const { income, balance, date, startDate, can } = useDashboardData()

  const cards = [
    {
      title: 'Revenue',
      value: income.data?.totalRevenue[0],
      tone: 'success' as const,
      caption: 'Revenue recorded in the general ledger this month.'
    },
    {
      title: 'Expenses',
      value: income.data?.totalExpense[0],
      tone: 'error' as const,
      caption: 'Expenses recorded in the general ledger this month.'
    },
    {
      title: 'Liabilities',
      value: balance.data?.totalLiabilities[0],
      tone: 'warning' as const,
      caption: 'Total liabilities as of the reporting date.'
    },
    {
      title: 'Equity',
      value: balance.data?.totalEquities[0],
      tone: 'info' as const,
      caption: 'Equity reported on the balance sheet.'
    }
  ]

  const links = [
    {
      label: 'Chart of Accounts',
      href: '/accounting/setup/accounts',
      ability: 'accounting.read',
      description: 'Maintain the accounts used in your books.'
    },
    {
      label: 'Journal Entries',
      href: '/accounting/transactions/journal-entries',
      ability: 'journal.read',
      description: 'Record, review, and post double-entry transactions.'
    },
    {
      label: 'General Ledger',
      href: '/accounting/books/ledger',
      ability: 'accounting.read',
      description: 'Trace account balances to their transactions.'
    },
    {
      label: 'Trial Balance',
      href: '/accounting/reports/trial-balance',
      ability: 'accounting.read',
      description: 'Review debit and credit balances.'
    },
    {
      label: 'Cash Flow',
      href: '/accounting/reports/cash-flow',
      ability: 'accounting.read',
      description: 'Review operating, investing, and financing cash movements.'
    },
    {
      label: 'Period Management',
      href: '/accounting/setup/periods',
      ability: 'period.read',
      description: 'Manage fiscal years and close accounting periods.'
    }
  ]

  return (
    <Stack spacing={4}>
      <Stack spacing={1}>
        <Typography variant='overline' color='text.secondary'>
          Dakila · Free Accounting
        </Typography>
        <Typography variant='h4'>Accounting overview</Typography>
        <Typography color='text.secondary'>
          Keep your books clear with journals, ledgers, and financial reports.
        </Typography>
        <Typography variant='body2' color='text.secondary'>
          Activity: {startDate} to {date}. Financial position as of {date}. Amounts are in your ledger’s base currency.
        </Typography>
      </Stack>
      {(income.isLoading || balance.isLoading) && <LinearProgress aria-label='Loading accounting reports' />}
      {(income.isError || balance.isError) && (
        <Alert
          severity='error'
          action={
            <Button
              color='inherit'
              onClick={() => {
                void income.refetch()
                void balance.refetch()
              }}
            >
              Retry
            </Button>
          }
        >
          Some accounting reports could not be loaded. Unavailable amounts are shown as a dash.
        </Alert>
      )}
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 6 }}>
          <FocusMetricCard
            title='Net income this month'
            value={amount(income.data?.netTotal[0])}
            tone='primary'
            helper='Revenue less expenses for the current month, using the same posted accounting data as your income statement.'
            href='/accounting/reports/income-statement'
            actionLabel='View income statement'
          />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <FocusMetricCard
            title='Total assets'
            value={amount(balance.data?.totalAssets[0])}
            tone='success'
            helper='Your financial position from the balance sheet, including current and non-current assets.'
            href='/accounting/reports/balance-sheet'
            actionLabel='View balance sheet'
          />
        </Grid>
        {cards.map(card => (
          <Grid key={card.title} size={{ xs: 12, sm: 6, xl: 3 }}>
            <DashboardKpiCard {...card} value={amount(card.value)} trend={[]} />
          </Grid>
        ))}
      </Grid>
      {income.isSuccess && income.data.accounts.length === 0 && (
        <Alert severity='info'>
          No income or expense activity for this month. Start with your chart of accounts and post a journal entry to
          build your reports.
        </Alert>
      )}
      <Card>
        <CardHeader
          title='Your accounting workspace'
          subheader='Core bookkeeping tools included in the free edition.'
        />
        <CardContent>
          <Grid container spacing={4}>
            {links
              .filter(link => can(link.ability))
              .map(link => (
                <Grid key={link.href} size={{ xs: 12, md: 4 }}>
                  <Stack spacing={1}>
                    <Typography variant='h6'>{link.label}</Typography>
                    <Typography variant='body2' color='text.secondary'>
                      {link.description}
                    </Typography>
                    <Button component={Link} href={link.href} sx={{ alignSelf: 'flex-start' }}>
                      Open
                    </Button>
                  </Stack>
                </Grid>
              ))}
          </Grid>
        </CardContent>
      </Card>
    </Stack>
  )
}
